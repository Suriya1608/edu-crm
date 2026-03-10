<?php

namespace App\Services\Telephony;

class TelephonyFactory
{
    public static function make(string $provider, array $config): TelephonyInterface
    {
        return match ($provider) {
            'exotel' => new ExotelService($config),
            // 'twilio' => new TwilioService($config), (later)
            default => throw new \Exception("Unsupported telephony provider"),
        };
    }
}
