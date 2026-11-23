<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Iso8601;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** Hanya baca. Tidak ada endpoint untuk mengubah atau menghapus audit log. */
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'action' => ['sometimes', 'nullable', 'string', 'max:100'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'from' => ['sometimes', 'nullable', 'string', 'regex:' . Iso8601::REGEX],
            'to' => ['sometimes', 'nullable', 'string', 'regex:' . Iso8601::REGEX],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $q = AuditLog::query()
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.username as actor');

        if (! empty($f['action'])) {
            $q->where('audit_logs.action', 'like', addcslashes($f['action'], '%_\\') . '%');
        }
        if (! empty($f['entity_type'])) {
            $q->where('audit_logs.entity_type', $f['entity_type']);
        }
        if (! empty($f['from'])) {
            $q->where('audit_logs.created_at', '>=', Iso8601::toUtc($f['from']));
        }
        if (! empty($f['to'])) {
            $q->where('audit_logs.created_at', '<=', Iso8601::toUtc($f['to']));
        }

        $paginator = $q->orderByDesc('audit_logs.id')->paginate((int) ($f['per_page'] ?? 50));
        $paginator->through(fn (AuditLog $l) => [
            'id' => $l->id,
            'actor' => $l->actor ?? 'sistem',
            'action' => $l->action,
            'entity_type' => $l->entity_type,
            'entity_id' => $l->entity_id,
            'old_value' => $l->old_value,
            'new_value' => $l->new_value,
            'ip_address' => $l->ip_address,
            'created_at' => $l->created_at?->toIso8601String(),
        ]);

        return response()->json($paginator->toArray());
    }
}
