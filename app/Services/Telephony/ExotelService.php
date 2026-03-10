<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Http;

class ExotelService implements TelephonyInterface
{
    protected $sid;
    protected $token;
    protected $subdomain;
    protected $callerId;

    public function __construct(array $config)
    {
        $this->sid = $config['sid'];
        $this->token = $config['token'];
        $this->subdomain = $config['subdomain'];
        $this->callerId = $config['caller_id'];
    }

    public function makeCall(string $from, string $to): array
    {
        $url = "https://{$this->sid}:{$this->token}@api.exotel.com/v1/Accounts/{$this->sid}/Calls/connect.json";

        $response = Http::asForm()->post($url, [
            'From' => $from,          // telecaller phone
            'To' => $to,              // lead phone
            'CallerId' => $this->callerId,
            'CallType' => 'trans',
        ]);

        return $response->json();
    }
}
