<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/** Writes an append-only trail of sensitive actions. */
class AuditLogger
{
    public static function log(string $action, ?Model $subject = null, array $details = [], $user = null): void
    {
        try {
            $request = request();
            $user ??= $request?->attributes->get('auth_user');

            AuditLog::create([
                'user_id' => $user?->user_id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'details' => $details ?: null,
                'ip_address' => $request?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Audit log write failed: ' . $e->getMessage());
        }
    }
}
