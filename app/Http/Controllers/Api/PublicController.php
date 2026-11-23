<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Election;
use App\Services\ResultService;
use Illuminate\Http\JsonResponse;

class PublicController extends Controller
{
    public function __construct(private ResultService $results)
    {
    }

    /** Status pemilihan untuk halaman login (tanpa angka partisipasi). */
    public function status(): JsonResponse
    {
        $election = Election::current();

        return response()->json(['data' => $election?->toApi()]);
    }

    /** Pengumuman yang sudah diterbitkan. */
    public function announcements(): JsonResponse
    {
        $data = Announcement::where('is_published', true)
            ->orderByDesc('published_at')
            ->limit(20)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'published_at' => $a->published_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $data]);
    }

    /** Hasil hanya tampil jika admin sudah mempublikasikannya. Golput hanya agregat, tanpa nama. */
    public function results(): JsonResponse
    {
        $election = Election::whereNotNull('results_published_at')->orderByDesc('id')->first();
        if (! $election) {
            throw new ApiException('Hasil pemilihan belum dipublikasikan.', 404, 'results_not_published');
        }

        return response()->json(['data' => [
            'election' => $election->toApi(),
            'turnout' => $election->final_summary['turnout'] ?? null,
            'results' => $this->results->tally($election->id),
        ]]);
    }
}
