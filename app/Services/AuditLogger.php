<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogger
{
    public static function log(string $action, ?string $description = null, ?string $module = null): void
    {
        $user = auth()->user();

        AuditLog::create([
            'user_id'    => $user?->id,
            'user_email' => $user?->email ?? 'system',
            'module'     => $module,
            'action'     => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
