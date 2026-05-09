<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Per-lead WhatsApp chat — managers/admins always; telecaller only if lead is assigned to them
Broadcast::channel('whatsapp.lead.{leadId}', function ($user, $leadId) {
    if (in_array($user->role, ['admin', 'manager'])) {
        return true;
    }
    if ($user->role === 'telecaller') {
        return \App\Models\Lead::where('id', $leadId)
            ->where('assigned_to', $user->id)
            ->exists();
    }
    return false;
});

// Per-user inbox channel — only the owner can subscribe
Broadcast::channel('whatsapp.inbox.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Per-telecaller dashboard refresh channel
Broadcast::channel('dashboard.telecaller.{tcId}', function ($user, $tcId) {
    return (int) $user->id === (int) $tcId || in_array($user->role, ['admin', 'manager']);
});
