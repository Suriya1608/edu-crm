<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\WhatsAppMessage;

class Lead extends Model
{
    protected $fillable = [
        'lead_code',
        'name',
        'phone',
        'email',
        'course_id',
        'source',
        'assigned_by',
        'assigned_to',
        'status',
        'next_followup',
        'sla_escalated_at',
        'is_duplicate',
        'merged_into_lead_id',
    ];

    protected $casts = [
        'sla_escalated_at'    => 'datetime',
        'is_duplicate'        => 'boolean',
        'merged_into_lead_id' => 'integer',
        'course_id'           => 'integer',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function enrolledCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function followups()
    {
        return $this->hasMany(Followup::class);
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * Latest activity — eager-loadable, avoids N+1 in lead lists.
     * Usage: Lead::with('lastActivity')->get()
     */
    public function lastActivity(): HasOne
    {
        return $this->hasOne(LeadActivity::class)->latestOfMany('created_at');
    }

    // ─── Computed Accessors ──────────────────────────────────────────────────────

    /**
     * Returns course name as a string.
     * Makes $lead->course backward-compatible across all views.
     * Requires 'enrolledCourse' to be eager-loaded in list queries to avoid N+1.
     */
    public function getCourseAttribute(): ?string
    {
        return $this->enrolledCourse?->name;
    }

    /**
     * Days since lead was created.
     * Used for aging indicators in UI.
     */
    public function getDaysAgedAttribute(): int
    {
        return (int) $this->created_at?->diffInDays(now()) ?? 0;
    }

    /**
     * Days since the last recorded activity on this lead.
     * Requires 'lastActivity' to be eager-loaded for N+1 safety.
     */
    public function getDaysSinceLastActivityAttribute(): int
    {
        $lastAt = $this->relationLoaded('lastActivity')
            ? $this->lastActivity?->created_at
            : $this->activities()->latest('created_at')->value('created_at');

        if (!$lastAt) {
            return $this->days_aged;
        }

        return (int) \Carbon\Carbon::parse($lastAt)->diffInDays(now());
    }
}
