<?php

namespace App\Support;

use App\Models\AuditLog;

class AuditLogger
{
    /** Catat tindakan admin/sistem. JANGAN PERNAH mencatat pilihan pemilih. */
    public static function record(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null,
    ): void {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => request()->ip(),
        ]);
    }
}
