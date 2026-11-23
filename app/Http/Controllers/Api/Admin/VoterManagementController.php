<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportVoterRequest;
use App\Http\Requests\StoreVoterRequest;
use App\Http\Requests\UpdateVoterRequest;
use App\Models\Voter;
use App\Services\VoterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoterManagementController extends Controller
{
    public function __construct(private VoterService $voters)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'type' => ['sometimes', 'nullable', Rule::in(['siswa', 'guru'])],
            'class_name' => ['sometimes', 'nullable', 'string', 'max:30'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $q = Voter::query();
        if (! empty($f['type'])) {
            $q->where('type', $f['type']);
        }
        if (! empty($f['class_name'])) {
            $q->where('class_name', $f['class_name']);
        }
        if (! empty($f['unit'])) {
            $q->where('unit', $f['unit']);
        }
        if (array_key_exists('is_active', $f) && $f['is_active'] !== null) {
            $q->where('is_active', (bool) $f['is_active']);
        }
        if (! empty($f['search'])) {
            $term = addcslashes($f['search'], '%_\\');
            $q->where('name', 'like', "%{$term}%");
        }

        $paginator = $q->orderBy('type')->orderBy('name')->paginate((int) ($f['per_page'] ?? 50));
        $paginator->through(fn (Voter $v) => $this->payload($v));

        $summary = [
            'siswa' => Voter::where('type', 'siswa')->where('is_active', true)->count(),
            'guru' => Voter::where('type', 'guru')->where('is_active', true)->count(),
        ];

        return response()->json(array_merge(['summary' => $summary], $paginator->toArray()));
    }

    public function store(StoreVoterRequest $request): JsonResponse
    {
        [$voter, $added] = $this->voters->create($request->validated(), $request->user());

        return response()->json(['data' => $this->payload($voter) + ['added_to_election' => $added]], 201);
    }

    public function update(UpdateVoterRequest $request, Voter $voter): JsonResponse
    {
        $voter = $this->voters->update($voter, $request->validated(), $request->user());

        return response()->json(['data' => $this->payload($voter)]);
    }

    public function destroy(Request $request, Voter $voter): JsonResponse
    {
        $this->voters->delete($voter, $request->user());

        return response()->json(['message' => 'Pemilih dihapus.']);
    }

    /** Impor CSV. Kirim dry_run=1 untuk memeriksa tanpa menyimpan. */
    public function import(ImportVoterRequest $request): JsonResponse
    {
        $result = $this->voters->import(
            $request->file('file'),
            $request->validated('type'),
            $request->boolean('dry_run'),
            $request->user(),
        );

        if (! $result['ok']) {
            return response()->json([
                'message' => 'File memiliki kesalahan. Tidak ada data yang diimpor.',
                'code' => 'import_invalid',
                'total' => $result['total'],
                'error_count' => $result['error_count'],
                'errors' => $result['errors'],
            ], 422);
        }

        unset($result['ok']);

        return response()->json(['data' => $result], $result['dry_run'] ? 200 : 201);
    }

    private function payload(Voter $v): array
    {
        return [
            'id' => $v->id,
            'type' => $v->type,
            'name' => $v->name,
            'class_name' => $v->class_name,
            'unit' => $v->unit,
            'is_active' => $v->is_active,
        ];
    }
}
