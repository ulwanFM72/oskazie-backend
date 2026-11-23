<?php

namespace App\Services;

use App\Models\ElectionVoter;
use App\Models\VoteParticipation;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung partisipasi dan golput. Golput = terdaftar - sudah memilih,
 * dihitung otomatis dan tidak pernah diubah manual.
 */
class TurnoutService
{
    public const REASONS = ['sakit', 'izin', 'dispensasi', 'pkl', 'lainnya', 'tanpa_keterangan'];

    public function compute(int $electionId): array
    {
        $registered = ElectionVoter::where('election_id', $electionId)->count();
        $voted = VoteParticipation::where('election_id', $electionId)->count();

        $attendance = ['hadir' => 0, 'tidak_hadir' => 0, 'belum_dicatat' => 0];
        $attendanceRows = ElectionVoter::where('election_id', $electionId)
            ->selectRaw('attendance_status, COUNT(*) AS total')
            ->groupBy('attendance_status')
            ->get();
        foreach ($attendanceRows as $r) {
            $attendance[$r->attendance_status] = (int) $r->total;
        }

        // Pemilih terdaftar yang TIDAK punya baris di vote_participations = golput.
        $rows = DB::table('election_voters as ev')
            ->leftJoin('vote_participations as vp', function ($join) {
                $join->on('vp.election_id', '=', 'ev.election_id')
                    ->on('vp.voter_id', '=', 'ev.voter_id');
            })
            ->where('ev.election_id', $electionId)
            ->whereNull('vp.id')
            ->selectRaw('ev.attendance_status, ev.absence_reason, COUNT(*) AS total')
            ->groupBy('ev.attendance_status', 'ev.absence_reason')
            ->get();

        $reasons = array_fill_keys(self::REASONS, 0);
        $notAttending = 0;
        $attendingNotVoting = 0;
        $noRecord = 0;

        foreach ($rows as $r) {
            $n = (int) $r->total;
            if ($r->attendance_status === 'tidak_hadir') {
                $notAttending += $n;
                $key = $r->absence_reason ?? 'tanpa_keterangan';
                $reasons[$key] = ($reasons[$key] ?? 0) + $n;
            } elseif ($r->attendance_status === 'hadir') {
                $attendingNotVoting += $n;
            } else {
                $noRecord += $n;
            }
        }

        return [
            'registered' => $registered,
            'voted' => $voted,
            'turnout_percent' => $registered > 0 ? round($voted / $registered * 100, 2) : 0.0,
            'golput_total' => $registered - $voted,
            'golput' => [
                'tidak_hadir' => ['total' => $notAttending, 'reasons' => $reasons],
                'hadir' => ['total' => $attendingNotVoting],
                'tanpa_catatan' => ['total' => $noRecord],
            ],
            'attendance' => $attendance,
        ];
    }
}
