<?php

namespace App\Services;

use App\Models\ElectionVoter;
use Illuminate\Database\Eloquent\Builder;

class ElectionVoterQuery
{
    private const VOTED_SQL = 'EXISTS (SELECT 1 FROM vote_participations vp
        WHERE vp.election_id = election_voters.election_id AND vp.voter_id = election_voters.voter_id)';

    public static function build(int $electionId, array $filters = [], bool $onlyNotVoted = false): Builder
    {
        $q = ElectionVoter::query()
            ->join('voters', 'voters.id', '=', 'election_voters.voter_id')
            ->where('election_voters.election_id', $electionId)
            ->select('election_voters.*', 'voters.name as voter_name')
            ->selectRaw(self::VOTED_SQL . ' AS has_voted');

        if ($onlyNotVoted) {
            $q->whereRaw('NOT ' . self::VOTED_SQL);
        }
        if (! empty($filters['class_name'])) {
            $q->where('election_voters.class_name', $filters['class_name']);
        }
        if (! empty($filters['unit'])) {
            $q->where('election_voters.unit', $filters['unit']);
        }
        if (! empty($filters['voter_type'])) {
            $q->where('election_voters.voter_type', $filters['voter_type']);
        }
        if (! empty($filters['attendance_status'])) {
            $q->where('election_voters.attendance_status', $filters['attendance_status']);
        }
        if (! empty($filters['search'])) {
            $term = addcslashes((string) $filters['search'], '%_\\');
            $q->where('voters.name', 'like', "%{$term}%");
        }

        return $q->orderBy('voters.name')->orderBy('election_voters.id');
    }

    public static function row(ElectionVoter $r): array
    {
        return [
            'id' => $r->id,
            'voter_name' => $r->voter_name,
            'voter_type' => $r->voter_type,
            'class_name' => $r->class_name,
            'unit' => $r->unit,
            'attendance_status' => $r->attendance_status,
            'absence_reason' => $r->absence_reason,
            'absence_note' => $r->absence_note,
            'recorded_at' => $r->recorded_at?->toIso8601String(),
            'has_voted' => (bool) $r->has_voted,
        ];
    }
}
