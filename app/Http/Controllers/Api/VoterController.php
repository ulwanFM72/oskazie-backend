<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CastVoteRequest;
use App\Models\Candidate;
use App\Models\Election;
use App\Services\VotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoterController extends Controller
{
    public function __construct(private VotingService $voting)
    {
    }

    public function candidates(Request $request): JsonResponse
    {
        $election = $this->openElection();

        $candidates = Candidate::where('election_id', $election->id)
            ->orderBy('number_order')
            ->get()
            ->map(fn (Candidate $c) => $c->toApi());

        return response()->json(['data' => [
            'election' => $election->toApi(),
            'candidates' => $candidates,
        ]]);
    }

    public function vote(CastVoteRequest $request): JsonResponse
    {
        $election = $this->openElection();
        $voter = $request->user();

        $this->voting->cast($voter, $election->id, (int) $request->validated('candidate_id'));

        // Selesai memilih: sesi diakhiri. Frontend kembali ke halaman login.
        $voter->tokens()->delete();

        return response()->json(['message' => 'Suara Anda telah tersimpan. Terima kasih.'], 201);
    }

    private function openElection(): Election
    {
        $election = Election::current();
        if (! $election || ! $election->isVotingOpen()) {
            throw new ApiException('Pemilihan tidak sedang berlangsung.', 403, 'election_closed');
        }

        return $election;
    }
}
