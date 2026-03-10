<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reusable, stateless audit-logging service.
 *
 * Design principles:
 *  - Never throws — wraps in try/catch so a logging failure never breaks a request
 *  - Silently skips if audit_logs table doesn't exist (safe during migrations)
 *  - Future SaaS: add $tenantId parameter when multi-tenancy is introduced
 *
 * Usage:
 *   AuditLogService::log('lead.status_changed', 'Lead', $lead->id, ['status' => 'new'], ['status' => 'contacted']);
 *   AuditLogService::log('user.force_logout', 'User', $targetUserId);
 */
class AuditLogService
{
    /**
     * Record an audit event.
     *
     * @param  string       $action    Dot-notation action name (e.g. "lead.assigned")
     * @param  string|null  $model     Eloquent model class short name (e.g. "Lead")
     * @param  int|null     $modelId   Primary key of the affected record
     * @param  array        $old       Snapshot of old values (empty array = not applicable)
     * @param  array        $new       Snapshot of new values (empty array = not applicable)
     */
    public static function log(
        string  $action,
        ?string $model   = null,
        ?int    $modelId = null,
        array   $old     = [],
        array   $new     = []
    ): void {
        try {
            if (!Schema::hasTable('audit_logs')) {
                return;
            }

            AuditLog::create([
                'user_id'    => Auth::id(),
                'action'     => $action,
                'model'      => $model,
                'model_id'   => $modelId,
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => Request::ip(),
            ]);
        } catch (Throwable) {
            // Silently swallow — audit failures must never break the application
        }
    }
}
