<?php

namespace App\Services;

use App\Models\Candidate;

class ResultService
{
    public function tally(int $electionId): array
    {
        $candidates = Candidate::where('election_id', $electionId)
            ->withCount('votes')
            ->orderBy('number_order')
            ->get();

        $total = (int) $candidates->sum('votes_count');

        $rows = $candidates->map(fn (Candidate $c) => [
            'candidate_id' => $c->id,
            'number_order' => $c->number_order,
            'chairman_name' => $c->chairman_name,
            'vice_name' => $c->vice_name,
            'photo_url' => $c->photoUrl(),
            'votes' => (int) $c->votes_count,
            'percent' => $total > 0 ? round($c->votes_count / $total * 100, 2) : 0.0,
        ])->sortByDesc('votes')->values();

        // Peringkat: suara sama = peringkat sama.
        $position = 0;
        $rank = 0;
        $prev = null;
        $ranked = $rows->map(function (array $row) use (&$position, &$rank, &$prev) {
            $position++;
            if ($row['votes'] !== $prev) {
                $rank = $position;
                $prev = $row['votes'];
            }
            $row['rank'] = $rank;

            return $row;
        })->all();

        return ['total_votes' => $total, 'candidates' => $ranked];
    }
}
