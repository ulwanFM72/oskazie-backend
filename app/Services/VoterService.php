<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\User;
use App\Models\Voter;
use App\Support\AuditLogger;
use App\Support\VoterIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class VoterService
{
    public const NIS_MIN = 5;
    public const NIS_MAX = 15;
    public const MAX_IMPORT_ROWS = 2000;

    /** @return array{0: Voter, 1: bool} [pemilih, ikut masuk DPT pemilihan berjalan?] */
    public function create(array $data, User $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $attrs = $this->attributes($data);
            $this->assertUnique($attrs);

            try {
                $voter = Voter::create($attrs);
            } catch (QueryException $e) {
                throw $this->duplicateOrRethrow($e);
            }

            $added = $this->addToCurrentElection($voter);

            AuditLogger::record($admin->id, 'voter.create', 'voter', $voter->id, null, $this->auditShape($voter) + ['added_to_election' => $added]);

            return [$voter, $added];
        });
    }

    public function update(Voter $voter, array $data, User $admin): Voter
    {
        return DB::transaction(function () use ($voter, $data, $admin) {
            $voter = Voter::lockForUpdate()->findOrFail($voter->id);
            $old = $this->auditShape($voter);
            $attrs = [];

            if (isset($data['name'])) {
                $attrs['name'] = $this->cleanName($data['name']);
            }

            if ($voter->type === 'siswa') {
                if (isset($data['nis'])) {
                    $attrs['nis_hash'] = VoterIdentity::hashNis($data['nis']);
                }
                if (isset($data['class_name'])) {
                    $attrs['class_name'] = trim($data['class_name']);
                }
            } else {
                // Mengganti nama TIDAK mengubah kunci login; ubah login_key secara eksplisit bila perlu.
                if (isset($data['login_key'])) {
                    $key = VoterIdentity::normalizeName($data['login_key']);
                    if ($key === '') {
                        throw new ApiException('Kunci login tidak valid.', 422, 'invalid_name');
                    }
                    $attrs['login_key'] = $key;
                }
                if (isset($data['unit'])) {
                    $attrs['unit'] = trim($data['unit']);
                }
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
                $attrs['is_active'] = (bool) $data['is_active'];
            }

            $this->assertUnique($attrs, $voter->id);

            try {
                $voter->update($attrs);
            } catch (QueryException $e) {
                throw $this->duplicateOrRethrow($e);
            }

            // Selama pemilihan belum difinalisasi, koreksi kelas/unit ikut ke snapshot DPT.
            if (isset($attrs['class_name']) || isset($attrs['unit'])) {
                $election = Election::whereNull('finalized_at')->orderByDesc('id')->first();
                if ($election) {
                    ElectionVoter::where('election_id', $election->id)
                        ->where('voter_id', $voter->id)
                        ->update([
                            'class_name' => $voter->class_name,
                            'unit' => $voter->unit,
                        ]);
                }
            }

            AuditLogger::record(
                $admin->id,
                'voter.update',
                'voter',
                $voter->id,
                $old,
                $this->auditShape($voter) + (isset($attrs['nis_hash']) ? ['nis_changed' => true] : []),
            );

            return $voter;
        });
    }

    public function delete(Voter $voter, User $admin): void
    {
        DB::transaction(function () use ($voter, $admin) {
            if (ElectionVoter::where('voter_id', $voter->id)->exists()) {
                throw new ApiException('Pemilih sudah masuk daftar pemilih tetap. Nonaktifkan saja, jangan dihapus.', 409, 'voter_in_dpt');
            }

            $old = $this->auditShape($voter);
            $voter->delete();

            AuditLogger::record($admin->id, 'voter.delete', 'voter', $voter->id, $old, null);
        });
    }

    /**
     * Impor CSV. Semua-atau-tidak-sama-sekali: jika ada satu baris salah, tidak ada yang disimpan.
     */
    public function import(UploadedFile $file, string $type, bool $dryRun, User $admin): array
    {
        $rows = $this->parseCsv($file, $type);

        $errors = [];
        $items = [];
        $seen = [];

        foreach ($rows as $row) {
            $rowType = mb_strtolower(trim($row['cells']['type'] ?? ''));
            if ($rowType !== '' && $rowType !== $type) {
                $errors[] = ['line' => $row['line'], 'message' => "Kolom type berisi '{$rowType}', tidak sesuai jenis impor '{$type}'."];
                continue;
            }

            [$data, $error] = $type === 'siswa'
                ? $this->validateStudentRow($row['cells'])
                : $this->validateTeacherRow($row['cells']);

            if ($error !== null) {
                $errors[] = ['line' => $row['line'], 'message' => $error];
                continue;
            }

            $attrs = $this->attributes($data);
            $key = $attrs['nis_hash'] ?? $attrs['login_key'];

            if (isset($seen[$key])) {
                $errors[] = ['line' => $row['line'], 'message' => "Duplikat dengan baris {$seen[$key]} pada file yang sama."];
                continue;
            }
            $seen[$key] = $row['line'];
            $items[] = ['line' => $row['line'], 'key' => $key, 'attrs' => $attrs];
        }

        // Duplikat terhadap data yang sudah ada di database.
        $column = $type === 'siswa' ? 'nis_hash' : 'login_key';
        $existing = [];
        foreach (array_chunk(array_keys($seen), 500) as $chunk) {
            foreach (Voter::whereIn($column, $chunk)->pluck($column) as $k) {
                $existing[$k] = true;
            }
        }
        $valid = [];
        foreach ($items as $item) {
            if (isset($existing[$item['key']])) {
                $errors[] = [
                    'line' => $item['line'],
                    'message' => $type === 'siswa' ? 'NIS sudah terdaftar di database.' : 'Nama lengkap sudah terdaftar di database.',
                ];
                continue;
            }
            $valid[] = $item['attrs'];
        }

        if ($errors) {
            usort($errors, fn ($a, $b) => $a['line'] <=> $b['line']);

            return [
                'ok' => false,
                'total' => count($rows),
                'error_count' => count($errors),
                'errors' => array_slice($errors, 0, 100),
            ];
        }

        if ($dryRun) {
            return ['ok' => true, 'dry_run' => true, 'total' => count($rows), 'valid' => count($valid)];
        }

        return DB::transaction(function () use ($valid, $type, $admin, $rows) {
            $added = 0;
            try {
                foreach ($valid as $attrs) {
                    $voter = Voter::create($attrs);
                    if ($this->addToCurrentElection($voter)) {
                        $added++;
                    }
                }
            } catch (QueryException $e) {
                throw $this->duplicateOrRethrow($e);
            }

            AuditLogger::record($admin->id, 'voter.import', 'voter', null, null, [
                'type' => $type,
                'imported' => count($valid),
                'added_to_election' => $added,
            ]);

            return [
                'ok' => true,
                'dry_run' => false,
                'total' => count($rows),
                'imported' => count($valid),
                'added_to_election' => $added,
            ];
        });
    }

    /**
     * Pemilih baru ikut pemilihan berjalan hanya jika pemilihan belum berakhir
     * (agar angka golput tidak berubah setelah waktu habis).
     */
    public function addToCurrentElection(Voter $voter): bool
    {
        if (! $voter->is_active) {
            return false;
        }

        $election = Election::whereNull('finalized_at')->orderByDesc('id')->first();
        if (! $election || ! in_array($election->status(), ['belum_dimulai', 'berlangsung'], true)) {
            return false;
        }

        ElectionVoter::firstOrCreate(
            ['election_id' => $election->id, 'voter_id' => $voter->id],
            ['voter_type' => $voter->type, 'class_name' => $voter->class_name, 'unit' => $voter->unit],
        );

        return true;
    }

    // ------------------------------------------------------------------

    private function attributes(array $data): array
    {
        $name = $this->cleanName($data['name']);

        if ($data['type'] === 'siswa') {
            return [
                'type' => 'siswa',
                'name' => $name,
                'nis_hash' => VoterIdentity::hashNis($data['nis']),
                'login_key' => null,
                'class_name' => trim($data['class_name']),
                'unit' => null,
            ];
        }

        // login_key eksplisit (mis. dari file impor) dipakai bila ada; jika tidak, diturunkan dari nama lengkap.
        $key = VoterIdentity::normalizeName($data['login_key'] ?? $name);
        if ($key === '') {
            throw new ApiException('Nama tidak valid.', 422, 'invalid_name');
        }

        return [
            'type' => 'guru',
            'name' => $name,
            'nis_hash' => null,
            'login_key' => $key,
            'class_name' => null,
            'unit' => trim($data['unit']),
        ];
    }

    private function assertUnique(array $attrs, ?int $ignoreId = null): void
    {
        if (! empty($attrs['nis_hash'])) {
            $q = Voter::where('nis_hash', $attrs['nis_hash']);
            if ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            }
            if ($q->exists()) {
                throw new ApiException('NIS sudah terdaftar.', 422, 'duplicate_nis');
            }
        }
        if (! empty($attrs['login_key'])) {
            $q = Voter::where('login_key', $attrs['login_key']);
            if ($ignoreId) {
                $q->where('id', '!=', $ignoreId);
            }
            if ($q->exists()) {
                throw new ApiException('Nama lengkap sudah terdaftar. Dua pemilih dengan nama yang sama tidak bisa dibedakan saat login.', 422, 'duplicate_name');
            }
        }
    }

    private function duplicateOrRethrow(QueryException $e): \Throwable
    {
        if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
            return new ApiException('Data pemilih duplikat (NIS atau nama sudah terdaftar).', 422, 'duplicate_voter');
        }

        return $e;
    }

    private function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function auditShape(Voter $voter): array
    {
        // Tidak pernah memuat NIS maupun hash NIS.
        return [
            'type' => $voter->type,
            'name' => $voter->name,
            'class_name' => $voter->class_name,
            'unit' => $voter->unit,
            'is_active' => $voter->is_active,
        ];
    }

    private function validateStudentRow(array $c): array
    {
        $name = $this->cleanName($c['nama'] ?? '');
        $nis = preg_replace('/\s+/', '', $c['nis'] ?? '');
        $class = trim($c['kelas'] ?? '');

        if ($name === '' || mb_strlen($name) > 150) {
            return [null, 'Nama kosong atau lebih dari 150 karakter.'];
        }
        if ($nis === '') {
            return [null, 'NIS kosong.'];
        }
        if (preg_match('/[eE.,]/', $nis)) {
            return [null, 'NIS rusak (tampak format ilmiah Excel). Format kolom NIS sebagai Teks, lalu simpan ulang sebagai CSV.'];
        }
        if (! preg_match('/^\d{' . self::NIS_MIN . ',' . self::NIS_MAX . '}$/', $nis)) {
            return [null, 'NIS harus berupa angka ' . self::NIS_MIN . ' sampai ' . self::NIS_MAX . ' digit.'];
        }
        if ($class === '' || mb_strlen($class) > 30) {
            return [null, 'Kelas kosong atau lebih dari 30 karakter.'];
        }

        return [['type' => 'siswa', 'name' => $name, 'nis' => $nis, 'class_name' => $class], null];
    }

    private function validateTeacherRow(array $c): array
    {
        $name = $this->cleanName($c['nama'] ?? '');
        $unit = trim($c['unit'] ?? '');
        $loginKey = trim($c['login_key'] ?? '');

        if ($name === '' || mb_strlen($name) > 150) {
            return [null, 'Nama kosong atau lebih dari 150 karakter.'];
        }
        if (VoterIdentity::normalizeName($loginKey !== '' ? $loginKey : $name) === '') {
            return [null, 'Nama atau login_key tidak valid.'];
        }
        if ($unit === '' || mb_strlen($unit) > 60) {
            return [null, 'Unit kosong atau lebih dari 60 karakter.'];
        }

        return [
            ['type' => 'guru', 'name' => $name, 'unit' => $unit] + ($loginKey !== '' ? ['login_key' => $loginKey] : []),
            null,
        ];
    }

    /** Baca CSV (koma atau titik koma, UTF-8 atau Windows-1252) menjadi baris asosiatif. */
    private function parseCsv(UploadedFile $file, string $type): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter);
        if (! $header) {
            throw new ApiException('File CSV kosong.', 422, 'import_empty');
        }
        $columns = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $header);
        // Nama kolom tabel voters juga diterima: name = nama, class_name = kelas.
        $aliases = ['name' => 'nama', 'class_name' => 'kelas'];
        $columns = array_map(fn ($c) => $aliases[$c] ?? $c, $columns);

        $required = $type === 'siswa' ? ['nama', 'nis', 'kelas'] : ['nama', 'unit'];
        $missing = array_values(array_diff($required, $columns));
        if ($missing) {
            throw new ApiException(
                'Kolom wajib tidak ditemukan: ' . implode(', ', $missing) . '. Header harus memuat: ' . implode(', ', $required) . ' (atau name / class_name).',
                422,
                'import_header',
            );
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($stream, 0, $delimiter)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // baris kosong
            }

            $assoc = [];
            foreach ($columns as $i => $col) {
                $assoc[$col] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }
            $rows[] = ['line' => $line, 'cells' => $assoc];

            if (count($rows) > self::MAX_IMPORT_ROWS) {
                fclose($stream);
                throw new ApiException('Terlalu banyak baris (maksimal ' . self::MAX_IMPORT_ROWS . ' per file).', 422, 'import_too_large');
            }
        }
        fclose($stream);

        if (! $rows) {
            throw new ApiException('Tidak ada data pada file.', 422, 'import_empty');
        }

        return $rows;
    }
}
