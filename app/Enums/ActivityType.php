<?php

namespace App\Enums;

enum ActivityType: string
{
    case Note         = 'note';
    case Call         = 'call';
    case Assignment   = 'assignment';
    case WhatsApp     = 'whatsapp';
    case Sms          = 'sms';
    case StatusChange = 'status_change';
    case Followup     = 'followup';
}
