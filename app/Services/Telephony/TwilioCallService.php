<?php

namespace App\Services\Telephony;

use App\Models\Setting;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;

class TwilioCallService
{
    public function generateToken(int $userId): string
    {
        $identity = 'agent_' . $userId;

        $token = new AccessToken(
            (string) Setting::getSecure('twilio_account_sid', env('TWILIO_ACCOUNT_SID')),
            (string) Setting::getSecure('twilio_api_key',     env('TWILIO_API_KEY')),
            (string) Setting::getSecure('twilio_api_secret',  env('TWILIO_API_SECRET')),
            3600,
            $identity
        );

        $grant = new VoiceGrant();
        $grant->setOutgoingApplicationSid((string) Setting::get('twilio_app_sid', env('TWILIO_APP_SID')));
        $grant->setIncomingAllow(true);
        $token->addGrant($grant);

        return $token->toJWT();
    }
}
