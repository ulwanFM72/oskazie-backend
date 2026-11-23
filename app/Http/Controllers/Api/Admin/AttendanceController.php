<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Election;
use App\Models\ElectionVoter;
use App\Services\AttendanceService;
use App\Services\ElectionVoterQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
    }

    /** DPT beserta status kehadiran, dengan filter dan pencarian. */
    public function index(Request $request, Election $election): JsonResponse
    {
        $filters = $this->filters($request);
        $perPage = (int) $request->query('per_page', 50);

        $paginator = ElectionVoterQuery::build($election->id, $filters)->paginate($perPage);
        $paginator->through(fn (ElectionVoter $r) => ElectionVoterQuery::row($r));

        $summary = ['hadir' => 0, 'tidak_hadir' => 0, 'belum_dicatat' => 0];
        $rows = ElectionVoter::where('election_id', $election->id)
            ->selectRaw('attendance_status, COUNT(*) AS total')
            ->groupBy('attendance_status')
            ->get();
        foreach ($rows as $r) {
            $summary[$r->attendance_status] = (int) $r->total;
        }

        return response()->json(array_merge(['summary' => $summary], $paginator->toArray()));
    }

    public function update(UpdateAttendanceRequest $request, ElectionVoter $electionVoter): JsonResponse
    {
        $ev = $this->attendance->record(
            $electionVoter->id,
            $request->validated('status'),
            $request->validated('absence_reason'),
            $request->validated('absence_note'),
            $request->user(),
        );

        return response()->json(['data' => [
            'id' => $ev->id,
            'attendance_status' => $ev->attendance_status,
            'absence_reason' => $ev->absence_reason,
            'absence_note' => $ev->absence_note,
            'recorded_at' => $ev->recorded_at?->toIso8601String(),
        ]]);
    }

    /** Daftar Belum Memilih (pemilih terdaftar tanpa baris partisipasi). */
    public function nonVoters(Request $request, Election $election): JsonResponse
    {
        $filters = $this->filters($request);
        $perPage = (int) $request->query('per_page', 50);

        $paginator = ElectionVoterQuery::build($election->id, $filters, true)->paginate($perPage);
        $paginator->through(fn (ElectionVoter $r) => ElectionVoterQuery::row($r));

        return response()->json($paginator->toArray());
    }

    public function exportNonVoters(Request $request, Election $election): StreamedResponse
    {
        $rows = ElectionVoterQuery::build($election->id, $this->filters($request), true)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Nama', 'Jenis', 'Kelas/Unit', 'Status kehadiran', 'Alasan']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $this->safe($r->voter_name),
                    $r->voter_type,
                    $this->safe((string) ($r->class_name ?? $r->unit ?? '')),
                    $r->attendance_status,
                    $r->absence_reason ?? '',
                ]);
            }
            fclose($out);
        }, "belum-memilih-{$election->id}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'class_name' => ['sometimes', 'nullable', 'string', 'max:30'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:60'],
            'voter_type' => ['sometimes', 'nullable', Rule::in(['siswa', 'guru'])],
            'attendance_status' => ['sometimes', 'nullable', Rule::in(['hadir', 'tidak_hadir', 'belum_dicatat'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);
    }

    /** Cegah CSV injection saat dibuka di Excel. */
    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
