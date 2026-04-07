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
            'tcn' => new TcnService([
                'client_id'     => (string) Setting::getSecure('tcn_client_id',     env('TCN_CLIENT_ID')),
                'client_secret' => (string) Setting::getSecure('tcn_client_secret', env('TCN_CLIENT_SECRET')),
                'refresh_token' => (string) Setting::getSecure('tcn_refresh_token', env('TCN_REFRESH_TOKEN')),
                'redirect_uri'  => (string) Setting::get('tcn_redirect_uri',        env('TCN_REDIRECT_URI')),
            ]),
            default => throw new \InvalidArgumentException("Unsupported telephony provider: {$provider}"),
        };
    }
}
