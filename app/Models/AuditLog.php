<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit trail for sensitive CRM actions.
 *
 * Designed for future SaaS expansion:
 *  - Add a `tenant_id` column + index when multi-tenancy is introduced
 *  - Never soft-delete or update records — audit logs are permanent
 */
class AuditLog extends Model
{
    // No updated_at column
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
