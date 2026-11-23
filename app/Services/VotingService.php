<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Vote;
use App\Models\VoteParticipation;
use App\Models\Voter;
use App\Support\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VotingService
{
    /**
     * Menyimpan satu suara. Semua validasi hak pilih terjadi di sini (backend).
     * - Kunci bersama pada baris pemilihan: finalisasi menunggu suara yang sedang diproses.
     * - Kunci eksklusif pada baris election_voters: request paralel dari pemilih yang sama diserialkan.
     * - UNIQUE (election_id, voter_id) pada vote_participations sebagai pengaman terakhir.
     */
    public function cast(Voter $voter, int $electionId, int $candidateId): void
    {
        try {
            DB::transaction(function () use ($voter, $electionId, $candidateId) {
                $election = Election::sharedLock()->find($electionId);
                if (! $election || ! $election->isVotingOpen()) {
                    throw new ApiException('Pemilihan tidak sedang berlangsung.', 403, 'election_closed');
                }

                $ev = ElectionVoter::where('election_id', $electionId)
                    ->where('voter_id', $voter->id)
                    ->lockForUpdate()
                    ->first();
                if (! $ev) {
                    throw new ApiException('Anda tidak terdaftar sebagai pemilih pada pemilihan ini.', 403, 'not_registered');
                }

                $alreadyVoted = VoteParticipation::where('election_id', $electionId)
                    ->where('voter_id', $voter->id)
                    ->exists();
                if ($alreadyVoted) {
                    throw new ApiException('Anda sudah menggunakan hak pilih.', 409, 'already_voted');
                }

                $candidateOk = Candidate::where('id', $candidateId)
                    ->where('election_id', $electionId)
                    ->exists();
                if (! $candidateOk) {
                    throw new ApiException('Pasangan calon tidak valid.', 422, 'invalid_candidate');
                }

                // Siapa yang memilih (waktu dibulatkan ke menit) dan apa pilihannya disimpan terpisah, tanpa penghubung.
                VoteParticipation::create([
                    'election_id' => $electionId,
                    'voter_id' => $voter->id,
                    'voted_at' => now()->startOfMinute(),
                ]);
                Vote::create([
                    'election_id' => $electionId,
                    'candidate_id' => $candidateId,
                ]);

                // Memilih berarti hadir.
                if ($ev->attendance_status !== 'hadir') {
                    $old = $ev->only(['attendance_status', 'absence_reason']);
                    $ev->update([
                        'attendance_status' => 'hadir',
                        'absence_reason' => null,
                        'absence_note' => null,
                        'recorded_by' => null,
                        'recorded_at' => now(),
                    ]);
                    if ($old['attendance_status'] === 'tidak_hadir') {
                        AuditLogger::record(null, 'attendance.auto_present', 'election_voter', $ev->id, $old, ['attendance_status' => 'hadir']);
                    }
                }
            }, 3);
        } catch (QueryException $e) {
            // 1062 = duplicate entry (UNIQUE election_id, voter_id): hak pilih sudah terpakai.
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new ApiException('Anda sudah menggunakan hak pilih.', 409, 'already_voted');
            }
            throw $e;
        }
    }
}
