<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'lead_id',
        'campaign_contact_id',
        'from_number',
        'message_body',
        'direction',
        'message',
        'provider_message_id',
        'sent_at',
        'meta_data',
        'is_read',
        'provider',
        'media_type',
        'media_url',
        'media_filename',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_read'   => 'boolean',
        'sent_at'   => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
