<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class LeadMeeting extends Model
{
    use Auditable;

    protected $fillable = [
        'lead_id', 'created_by', 'title', 'meeting_link',
        'google_event_id', 'zoom_meeting_id', 'meeting_time', 'duration',
        'notes', 'status', 'meeting_type', 'whatsapp_sent',
    ];

    protected $casts = [
        'meeting_time'  => 'datetime',
        'whatsapp_sent' => 'boolean',
        'duration'      => 'integer',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
