<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\User;
use App\Models\VoteParticipation;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    private const FIELDS = ['attendance_status', 'absence_reason', 'absence_note'];

    public function record(
        int $electionVoterId,
        string $status,
        ?string $reason,
        ?string $note,
        User $admin,
    ): ElectionVoter {
        return DB::transaction(function () use ($electionVoterId, $status, $reason, $note, $admin) {
            // Baris yang sama dikunci oleh VotingService, jadi tidak ada balapan dengan proses memilih.
            $ev = ElectionVoter::lockForUpdate()->findOrFail($electionVoterId);
            $election = Election::findOrFail($ev->election_id);

            if ($election->isFinalized()) {
                throw new ApiException('Pemilihan sudah difinalisasi; kehadiran tidak dapat diubah.', 409, 'election_finalized');
            }

            $alreadyVoted = VoteParticipation::where('election_id', $ev->election_id)
                ->where('voter_id', $ev->voter_id)
                ->exists();
            if ($alreadyVoted) {
                throw new ApiException('Pemilih sudah memilih; kehadiran otomatis tercatat hadir.', 409, 'already_voted');
            }

            $old = $ev->only(self::FIELDS);

            $ev->fill([
                'attendance_status' => $status,
                'absence_reason' => $status === 'tidak_hadir' ? $reason : null,
                'absence_note' => $status === 'tidak_hadir' ? $note : null,
                'recorded_by' => $status === 'belum_dicatat' ? null : $admin->id,
                'recorded_at' => $status === 'belum_dicatat' ? null : now(),
            ])->save();

            AuditLogger::record($admin->id, 'attendance.update', 'election_voter', $ev->id, $old, $ev->only(self::FIELDS));

            return $ev;
        });
    }
}
