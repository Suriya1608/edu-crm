<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramConversation extends Model
{
    protected $fillable = [
        'instagram_account_id', 'sender_id', 'sender_name', 'sender_username',
        'last_message_preview', 'last_message_at', 'unread_count', 'assigned_to',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'unread_count'    => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    public function messages()
    {
        return $this->hasMany(InstagramMessage::class, 'conversation_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
