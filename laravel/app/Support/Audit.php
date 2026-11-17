<?php

namespace App\Support;

use App\Models\AuditLog;

/** Writes the audit trail, ported from server/src/lib/audit.ts. */
class Audit
{
    public static function log(string $action, string $entity, ?string $entityId, string $summary, ?array $before = null, ?array $after = null, ?string $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'summary' => $summary,
            'before' => $before,
            'after' => $after,
            'ip' => request()?->ip(),
        ]);
    }
}
