<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\Election;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateManagementController extends Controller
{
    public function __construct(private CandidateService $candidates)
    {
    }

    /** Paslon pemilihan tertentu (election_id), default pemilihan terbaru. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['election_id' => ['sometimes', 'integer', 'exists:elections,id']]);

        $electionId = $request->query('election_id') ?? Election::current()?->id;
        if (! $electionId) {
            return response()->json(['data' => []]);
        }

        $data = Candidate::where('election_id', $electionId)
            ->orderBy('number_order')
            ->get()
            ->map(fn (Candidate $c) => $c->toApi() + ['election_id' => $c->election_id]);

        return response()->json(['data' => $data]);
    }

    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $candidate = $this->candidates->create(
            $request->safe()->except('photo'),
            $request->file('photo'),
            $request->user(),
        );

        return response()->json(['data' => $candidate->toApi() + ['election_id' => $candidate->election_id]], 201);
    }

    /** Untuk unggah foto, kirim multipart dengan POST + field _method=PATCH. */
    public function update(UpdateCandidateRequest $request, Candidate $candidate): JsonResponse
    {
        $candidate = $this->candidates->update(
            $candidate->id,
            $request->safe()->except('photo'),
            $request->file('photo'),
            $request->user(),
        );

        return response()->json(['data' => $candidate->toApi() + ['election_id' => $candidate->election_id]]);
    }

    public function destroy(Request $request, Candidate $candidate): JsonResponse
    {
        $this->candidates->delete($candidate->id, $request->user());

        return response()->json(['message' => 'Paslon dihapus.']);
    }
}
