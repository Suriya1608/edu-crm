<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsAppMessagePushed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int   $leadId,
        public readonly int   $assignedTo,
        public readonly array $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            // Lead detail page (WaChat component subscribes here)
            new PrivateChannel("whatsapp.lead.{$this->leadId}"),
            // Inbox toast notifications (layout subscribes here)
            new PrivateChannel("whatsapp.inbox.{$this->assignedTo}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    public function broadcastWith(): array
    {
        return $this->message;
    }
}
