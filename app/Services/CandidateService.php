<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CandidateService
{
    private const FIELDS = [
        'number_order', 'chairman_name', 'chairman_class', 'vice_name', 'vice_class',
        'vision', 'mission', 'work_program', 'profile',
    ];

    /** Paslon hanya boleh ditambah sebelum pemilihan dimulai. */
    public function create(array $data, ?UploadedFile $photo, User $admin): Candidate
    {
        return DB::transaction(function () use ($data, $photo, $admin) {
            $election = isset($data['election_id'])
                ? Election::lockForUpdate()->findOrFail($data['election_id'])
                : $this->currentElection();

            if ($election->status() !== 'belum_dimulai') {
                throw new ApiException('Paslon hanya dapat ditambah sebelum pemilihan dimulai.', 409, 'election_started');
            }
            $this->assertNumberFree($election->id, (int) $data['number_order']);

            $candidate = Candidate::create(['election_id' => $election->id] + array_intersect_key($data, array_flip(self::FIELDS)));

            if ($photo) {
                $candidate->update(['photo_path' => $photo->store('candidates', 'public')]);
            }

            AuditLogger::record($admin->id, 'candidate.create', 'candidate', $candidate->id, null, $this->shape($candidate));

            return $candidate;
        });
    }

    /** Teks dan foto bisa dikoreksi sampai finalisasi. Nomor urut hanya sebelum pemilihan dimulai. */
    public function update(int $candidateId, array $data, ?UploadedFile $photo, User $admin): Candidate
    {
        $oldPhoto = null;

        $candidate = DB::transaction(function () use ($candidateId, $data, $photo, $admin, &$oldPhoto) {
            $candidate = Candidate::lockForUpdate()->findOrFail($candidateId);
            $election = Election::lockForUpdate()->findOrFail($candidate->election_id);

            if ($election->isFinalized()) {
                throw new ApiException('Pemilihan sudah difinalisasi; data paslon terkunci.', 409, 'election_finalized');
            }

            $old = $this->shape($candidate);
            $attrs = array_intersect_key($data, array_flip(self::FIELDS));

            if (isset($attrs['number_order']) && (int) $attrs["number_order"] !== (int) $candidate->number_order) {
                if ($election->status() !== 'belum_dimulai') {
                    throw new ApiException('Nomor urut tidak dapat diubah setelah pemilihan dimulai.', 409, 'election_started');
                }
                $this->assertNumberFree($election->id, (int) $attrs['number_order'], $candidate->id);
            }

            if ($photo) {
                $oldPhoto = $candidate->photo_path;
                $attrs['photo_path'] = $photo->store('candidates', 'public');
            } elseif (! empty($data['remove_photo'])) {
                $oldPhoto = $candidate->photo_path;
                $attrs['photo_path'] = null;
            }

            $candidate->update($attrs);

            AuditLogger::record($admin->id, 'candidate.update', 'candidate', $candidate->id, $old, $this->shape($candidate));

            return $candidate;
        });

        if ($oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return $candidate;
    }

    public function delete(int $candidateId, User $admin): void
    {
        $photo = null;

        DB::transaction(function () use ($candidateId, $admin, &$photo) {
            $candidate = Candidate::lockForUpdate()->findOrFail($candidateId);
            $election = Election::lockForUpdate()->findOrFail($candidate->election_id);

            if ($election->status() !== 'belum_dimulai') {
                throw new ApiException('Paslon hanya dapat dihapus sebelum pemilihan dimulai.', 409, 'election_started');
            }

            $old = $this->shape($candidate);
            $photo = $candidate->photo_path;
            $candidate->delete();

            AuditLogger::record($admin->id, 'candidate.delete', 'candidate', $candidateId, $old, null);
        });

        if ($photo) {
            Storage::disk('public')->delete($photo);
        }
    }

    private function currentElection(): Election
    {
        $election = Election::current();
        if (! $election) {
            throw new ApiException('Buat pemilihan terlebih dahulu.', 404, 'no_election');
        }

        return Election::lockForUpdate()->findOrFail($election->id);
    }

    private function assertNumberFree(int $electionId, int $number, ?int $ignoreId = null): void
    {
        $q = Candidate::where('election_id', $electionId)->where('number_order', $number);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->exists()) {
            throw new ApiException("Nomor urut {$number} sudah dipakai paslon lain.", 422, 'duplicate_number');
        }
    }

    private function shape(Candidate $c): array
    {
        return $c->only(self::FIELDS);
    }
}
