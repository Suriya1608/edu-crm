<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExotelService implements TelephonyInterface
{
    protected string $apiKey;
    protected string $apiToken;
    protected string $sid;
    protected string $subdomain;
    protected string $callerId;

    public function __construct(array $config)
    {
        $this->apiKey    = $config['api_key'];
        $this->apiToken  = $config['api_token'];
        $this->sid       = $config['sid'];
        $this->subdomain = $config['subdomain'];
        $this->callerId  = $config['caller_id'];
    }

    /**
     * Initiate a call via Exotel Connect API.
     *
     * Exotel calls $from (telecaller's mobile) first, then bridges to $to (lead).
     * $callerId is the Exotel number the lead will see.
     *
     * @param  string      $from           Telecaller's mobile number
     * @param  string      $to             Lead's phone number
     * @param  string|null $statusCallback Webhook URL for status updates
     * @return array
     */
    public function makeCall(string $from, string $to, ?string $statusCallback = null): array
    {
        $url = "https://{$this->subdomain}/v1/Accounts/{$this->sid}/Calls/connect";

        $params = [
            'From'     => $from,
            'To'       => $to,
            'CallerId' => $this->callerId,
            'CallType' => 'trans',
        ];

        if ($statusCallback) {
            $params['StatusCallback'] = $statusCallback;
        }

        $response = Http::withBasicAuth($this->apiKey, $this->apiToken)
            ->asForm()
            ->post($url, $params);

        Log::info('[ExotelService] makeCall response', [
            'http_status' => $response->status(),
            'raw_body'    => $response->body(),
            'parsed_json' => $response->json(),
            'url'         => $url,
            'from'        => $params['From'],
            'to'          => $params['To'],
            'caller_id'   => $params['CallerId'],
        ]);

        if ($response->failed()) {
            Log::error('[ExotelService] makeCall failed', [
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ]);
        }

        return $response->json() ?? [];
    }
}
