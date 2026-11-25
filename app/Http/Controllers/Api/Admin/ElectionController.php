<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyElectionRequest;
use App\Http\Requests\EndElectionRequest;
use App\Http\Requests\ExtendElectionRequest;
use App\Http\Requests\StoreElectionRequest;
use App\Http\Requests\UpdateElectionRequest;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\VoteParticipation;
use App\Services\ElectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElectionController extends Controller
{
    public function __construct(private ElectionService $elections)
    {
    }

    public function store(StoreElectionRequest $request): JsonResponse
    {
        $election = $this->elections->create($request->validated(), $request->user());

        return response()->json(['data' => $election->toApi()], 201);
    }

    public function current(): JsonResponse
    {
        $election = Election::current();
        if (! $election) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $election->toApi() + [
            'registered' => ElectionVoter::where('election_id', $election->id)->count(),
            'voted' => VoteParticipation::where('election_id', $election->id)->count(),
        ]]);
    }

    public function update(UpdateElectionRequest $request, Election $election): JsonResponse
    {
        $election = $this->elections->update($election->id, $request->validated(), $request->user());

        return response()->json(['data' => $election->toApi()]);
    }

    public function extend(ExtendElectionRequest $request, Election $election): JsonResponse
    {
        $election = $this->elections->extend($election->id, $request->validated('end_at'), $request->user());

        return response()->json(['data' => $election->toApi()]);
    }

    /** Akhiri sekarang (tanpa end_at) atau percepat (dengan end_at). Alasan wajib. */
    public function endEarly(EndElectionRequest $request, Election $election): JsonResponse
    {
        $election = $this->elections->endEarly(
            $election->id,
            $request->validated('end_at'),
            $request->validated('reason'),
            $request->user(),
        );

        return response()->json(['data' => $election->toApi()]);
    }

    public function finalize(Request $request, Election $election): JsonResponse
    {
        $election = $this->elections->finalize($election->id, $request->user());

        return response()->json(['data' => $election->toApi() + [
            'turnout' => $election->final_summary['turnout'] ?? null,
        ]]);
    }

    public function publish(Request $request, Election $election): JsonResponse
    {
        $election = $this->elections->publish($election->id, $request->user());

        return response()->json(['data' => $election->toApi()]);
    }

    /** Hapus pemilihan (dan datanya). Wajib konfirmasi HAPUS. */
    public function destroy(DestroyElectionRequest $request, Election $election): JsonResponse
    {
        $summary = $this->elections->delete($election->id, $request->user());

        return response()->json(['data' => $summary]);
    }
}
