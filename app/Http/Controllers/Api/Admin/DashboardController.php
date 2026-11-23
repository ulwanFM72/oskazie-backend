<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Election;
use App\Services\TurnoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private TurnoutService $turnout)
    {
    }

    /** Ringkasan partisipasi. Tidak memuat suara per paslon. */
    public function show(): JsonResponse
    {
        $election = Election::current();
        if (! $election) {
            return response()->json(['data' => null]);
        }

        $final = $election->isFinalized();
        $turnout = $final
            ? ($election->final_summary['turnout'] ?? $this->turnout->compute($election->id))
            : $this->turnout->compute($election->id);

        $groups = DB::table('election_voters as ev')
            ->leftJoin('vote_participations as vp', function ($join) {
                $join->on('vp.election_id', '=', 'ev.election_id')
                    ->on('vp.voter_id', '=', 'ev.voter_id');
            })
            ->where('ev.election_id', $election->id)
            ->selectRaw('ev.voter_type, COALESCE(ev.class_name, ev.unit, \'-\') AS grp, COUNT(*) AS registered, COUNT(vp.id) AS voted')
            ->groupBy('ev.voter_type', 'grp')
            ->orderBy('ev.voter_type')
            ->orderBy('grp')
            ->get();

        $map = fn ($r) => [
            'name' => $r->grp,
            'registered' => (int) $r->registered,
            'voted' => (int) $r->voted,
            'percent' => $r->registered > 0 ? round($r->voted / $r->registered * 100, 2) : 0.0,
        ];

        return response()->json(['data' => [
            'election' => $election->toApi(),
            'is_final' => $final,
            'candidates_count' => Candidate::where('election_id', $election->id)->count(),
            'totals' => [
                'registered' => $turnout['registered'],
                'voted' => $turnout['voted'],
                'not_voted' => $turnout['golput_total'],
                'turnout_percent' => $turnout['turnout_percent'],
            ],
            'attendance' => $turnout['attendance'],
            'by_class' => $groups->where('voter_type', 'siswa')->map($map)->values(),
            'by_unit' => $groups->where('voter_type', 'guru')->map($map)->values(),
        ]]);
    }
}
