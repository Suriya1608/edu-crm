<?php

namespace App\Services\Telephony;

class TelephonyFactory
{
    public static function make(string $provider): TelephonyInterface
    {
        return match ($provider) {
            'tcn' => new TcnService([
                'client_id'     => (string) config('tcn.client_id',     env('TCN_CLIENT_ID')),
                'client_secret' => (string) config('tcn.client_secret', env('TCN_CLIENT_SECRET')),
                'refresh_token' => (string) config('tcn.refresh_token', env('TCN_REFRESH_TOKEN')),
                'redirect_uri'  => (string) config('tcn.redirect_uri',  env('TCN_REDIRECT_URI')),
            ]),
            default => throw new \InvalidArgumentException("Unsupported telephony provider: {$provider}"),
        };
    }
}
