<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\User;
use App\Models\Voter;
use App\Support\AuditLogger;
use App\Support\Iso8601;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ElectionService
{
    public function __construct(private TurnoutService $turnout)
    {
    }

    /** Membuat pemilihan dan snapshot DPT dari pemilih aktif. */
    public function create(array $data, User $admin): Election
    {
        return DB::transaction(function () use ($data, $admin) {
            if (Election::whereNull('finalized_at')->exists()) {
                throw new ApiException('Masih ada pemilihan yang belum difinalisasi.', 409, 'election_exists');
            }
            if (! Voter::where('is_active', true)->exists()) {
                throw new ApiException('Belum ada data pemilih aktif.', 422, 'no_voters');
            }

            $election = Election::create([
                'name' => $data['name'],
                'start_at' => Iso8601::toUtc($data['start_at']),
                'end_at' => Iso8601::toUtc($data['end_at']),
                'created_by' => $admin->id,
            ]);

            $now = now();
            Voter::where('is_active', true)->orderBy('id')->chunkById(500, function ($voters) use ($election, $now) {
                ElectionVoter::insert($voters->map(fn (Voter $v) => [
                    'election_id' => $election->id,
                    'voter_id' => $v->id,
                    'voter_type' => $v->type,
                    'class_name' => $v->class_name,
                    'unit' => $v->unit,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            AuditLogger::record($admin->id, 'election.create', 'election', $election->id, null, $this->snapshot($election));

            return $election;
        });
    }

    /** Ubah nama/jadwal, hanya sebelum pemilihan dimulai. */
    public function update(int $electionId, array $data, User $admin): Election
    {
        return DB::transaction(function () use ($electionId, $data, $admin) {
            $election = Election::lockForUpdate()->findOrFail($electionId);

            if ($election->status() !== 'belum_dimulai') {
                throw new ApiException('Jadwal hanya bisa diubah sebelum pemilihan dimulai. Gunakan perpanjangan untuk menambah waktu.', 409, 'election_started');
            }

            $old = $this->snapshot($election);

            $start = isset($data['start_at']) ? Iso8601::toUtc($data['start_at']) : $election->start_at;
            $end = isset($data['end_at']) ? Iso8601::toUtc($data['end_at']) : $election->end_at;
            if ($end->lte($start)) {
                throw new ApiException('Waktu berakhir harus setelah waktu mulai.', 422, 'invalid_period');
            }

            $election->update([
                'name' => $data['name'] ?? $election->name,
                'start_at' => $start,
                'end_at' => $end,
            ]);

            AuditLogger::record($admin->id, 'election.update', 'election', $election->id, $old, $this->snapshot($election));

            return $election;
        });
    }

    /** Perpanjang end_at selama belum finalisasi (boleh juga setelah end_at lewat). */
    public function extend(int $electionId, string $newEnd, User $admin): Election
    {
        return DB::transaction(function () use ($electionId, $newEnd, $admin) {
            $election = Election::lockForUpdate()->findOrFail($electionId);

            if ($election->isFinalized()) {
                throw new ApiException('Pemilihan sudah difinalisasi; waktu tidak dapat diubah.', 409, 'election_finalized');
            }

            $end = Iso8601::toUtc($newEnd);
            if ($end->lte($election->end_at)) {
                throw new ApiException('Waktu berakhir baru harus lebih akhir dari waktu berakhir saat ini.', 422, 'invalid_period');
            }
            if ($end->lte(now())) {
                throw new ApiException('Waktu berakhir baru harus di masa depan.', 422, 'invalid_period');
            }

            $old = $this->snapshot($election);
            $election->update(['end_at' => $end]);

            AuditLogger::record($admin->id, 'election.extend', 'election', $election->id, $old, $this->snapshot($election));

            return $election;
        });
    }

    /**
     * Akhiri atau percepat pemilihan yang SEDANG berlangsung (kondisi khusus).
     * $newEnd null = akhiri sekarang; diisi = percepat ke waktu itu (harus di masa depan dan lebih awal dari end_at).
     * Alasan wajib dan dicatat di audit log. Selama belum difinalisasi, waktu masih dapat diperpanjang lagi.
     */
    public function endEarly(int $electionId, ?string $newEnd, string $reason, User $admin): Election
    {
        return DB::transaction(function () use ($electionId, $newEnd, $reason, $admin) {
            // Kunci eksklusif menunggu suara yang sedang diproses; setelah commit, suara baru ditolak.
            $election = Election::lockForUpdate()->findOrFail($electionId);

            if ($election->isFinalized()) {
                throw new ApiException('Pemilihan sudah difinalisasi; waktu tidak dapat diubah.', 409, 'election_finalized');
            }
            if ($election->status() !== 'berlangsung') {
                throw new ApiException('Hanya pemilihan yang sedang berlangsung yang dapat dipercepat atau diakhiri.', 409, 'election_not_running');
            }

            $now = now();

            if ($newEnd === null) {
                $end = $now->copy();
                if ($end->lte($election->start_at)) {
                    $end = $election->start_at->copy()->addSecond(); // jaga end_at > start_at
                }
            } else {
                $end = Iso8601::toUtc($newEnd);
                if ($end->lte($now)) {
                    throw new ApiException('Waktu baru harus di masa depan. Untuk mengakhiri sekarang, pilih Akhiri sekarang.', 422, 'invalid_period');
                }
                if ($end->gte($election->end_at)) {
                    throw new ApiException('Waktu baru harus lebih awal dari waktu berakhir saat ini. Untuk menambah waktu gunakan perpanjangan.', 422, 'invalid_period');
                }
            }

            $old = $this->snapshot($election);
            $election->update(['end_at' => $end]);

            AuditLogger::record(
                $admin->id,
                'election.end_early',
                'election',
                $election->id,
                $old,
                $this->snapshot($election) + [
                    'mode' => $newEnd === null ? 'akhiri_sekarang' : 'dipercepat',
                    'reason' => $reason,
                ],
            );

            return $election;
        });
    }

    /** Finalisasi: hitung golput otomatis, bekukan ringkasan, kunci data. Tidak bisa dibatalkan. */
    public function finalize(int $electionId, User $admin): Election
    {
        return DB::transaction(function () use ($electionId, $admin) {
            // Kunci eksklusif menunggu semua suara yang sedang diproses (VotingService memakai kunci bersama).
            $election = Election::lockForUpdate()->findOrFail($electionId);

            if ($election->isFinalized()) {
                throw new ApiException('Pemilihan sudah difinalisasi.', 409, 'election_finalized');
            }
            if ($election->status() !== 'selesai') {
                throw new ApiException('Pemilihan belum berakhir, belum dapat difinalisasi.', 409, 'election_not_ended');
            }

            $summary = [
                'turnout' => $this->turnout->compute($election->id),
                'computed_at' => now()->toIso8601String(),
            ];

            $election->update([
                'finalized_at' => now(),
                'final_summary' => $summary,
            ]);

            AuditLogger::record($admin->id, 'election.finalize', 'election', $election->id, null, $summary['turnout']);

            return $election;
        });
    }

    public function publish(int $electionId, User $admin): Election
    {
        return DB::transaction(function () use ($electionId, $admin) {
            $election = Election::lockForUpdate()->findOrFail($electionId);

            if (! $election->isFinalized()) {
                throw new ApiException('Hasil hanya dapat dipublikasikan setelah finalisasi.', 409, 'election_not_final');
            }
            if ($election->results_published_at !== null) {
                throw new ApiException('Hasil sudah dipublikasikan.', 409, 'already_published');
            }

            $election->update(['results_published_at' => now()]);

            AuditLogger::record($admin->id, 'election.publish', 'election', $election->id);

            return $election;
        });
    }

    /**
     * Hapus pemilihan beserta suara, catatan partisipasi, DPT, dan paslonnya.
     * Data pemilih (voters), akun admin, dan audit log TIDAK ikut terhapus.
     * Pemilihan yang sedang berlangsung tidak boleh dihapus (akhiri dulu).
     */
    public function delete(int $electionId, User $admin): array
    {
        $photos = [];

        $summary = DB::transaction(function () use ($electionId, $admin, &$photos) {
            $election = Election::lockForUpdate()->findOrFail($electionId);

            if ($election->status() === 'berlangsung') {
                throw new ApiException('Pemilihan yang sedang berlangsung tidak dapat dihapus. Akhiri pemilihan terlebih dahulu.', 409, 'election_running');
            }

            $photos = DB::table('candidates')->where('election_id', $election->id)->whereNotNull('photo_path')->pluck('photo_path')->all();

            // Urutan mengikuti foreign key: votes -> vote_participations -> election_voters -> candidates -> elections.
            $summary = [
                'name' => $election->name,
                'status' => $election->status(),
                'votes' => DB::table('votes')->where('election_id', $election->id)->delete(),
                'participations' => DB::table('vote_participations')->where('election_id', $election->id)->delete(),
                'registered' => DB::table('election_voters')->where('election_id', $election->id)->delete(),
                'candidates' => DB::table('candidates')->where('election_id', $election->id)->delete(),
            ];
            $election->delete();

            AuditLogger::record($admin->id, 'election.delete', 'election', $electionId, $this->snapshot($election), $summary);

            return $summary;
        });

        foreach ($photos as $photo) {
            Storage::disk('public')->delete($photo);
        }

        return $summary;
    }

    private function snapshot(Election $election): array
    {
        return [
            'name' => $election->name,
            'start_at' => $election->start_at->toIso8601String(),
            'end_at' => $election->end_at->toIso8601String(),
        ];
    }
}
