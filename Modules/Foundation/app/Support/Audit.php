<?php

namespace Modules\Foundation\Support;

use Modules\Foundation\Models\AuditLog;

class Audit
{
    public static function record(string $action, ?object $entity = null, ?array $before = null, ?array $after = null, ?int $branchId = null): void
    {
        $request = app('request');

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'branch_id' => $branchId,
            'created_at' => now(),
        ]);
    }
}
