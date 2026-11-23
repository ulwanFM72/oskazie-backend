<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\ResultService;
use App\Services\TurnoutService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private TurnoutService $turnout, private ResultService $results)
    {
    }

    /** Partisipasi dan golput. Sebelum finalisasi hanya proyeksi (is_final = false). */
    public function turnout(Election $election): JsonResponse
    {
        $final = $election->isFinalized();
        $data = $final
            ? ($election->final_summary['turnout'] ?? $this->turnout->compute($election->id))
            : $this->turnout->compute($election->id);

        return response()->json(['data' => [
            'election' => $election->toApi(),
            'is_final' => $final,
            'turnout' => $data,
        ]]);
    }

    /** Suara per paslon baru terlihat oleh admin setelah finalisasi. */
    public function results(Election $election): JsonResponse
    {
        if (! $election->isFinalized()) {
            throw new ApiException('Hasil per paslon baru dapat dilihat setelah finalisasi.', 403, 'results_locked');
        }

        return response()->json(['data' => [
            'election' => $election->toApi(),
            'turnout' => $election->final_summary['turnout'] ?? null,
            'results' => $this->results->tally($election->id),
        ]]);
    }
}
