<?php

namespace App\Services\Telephony;

use App\Models\Setting;

class TelephonyFactory
{
    public static function make(string $provider): TelephonyInterface
    {
        return match ($provider) {
            'exotel' => new ExotelService([
                'api_key'   => (string) Setting::getSecure('exotel_api_key',   env('EXOTEL_API_KEY')),
                'api_token' => (string) Setting::getSecure('exotel_api_token',  env('EXOTEL_TOKEN')),
                'sid'       => (string) Setting::get('exotel_sid',              env('EXOTEL_SID')),
                'subdomain' => (string) Setting::get('exotel_subdomain',        env('EXOTEL_SUBDOMAIN', 'api.in.exotel.com')),
                'caller_id' => (string) Setting::get('exotel_caller_id',        env('EXOTEL_FROM')),
            ]),
            default => throw new \InvalidArgumentException("Unsupported telephony provider: {$provider}"),
        };
    }
}
