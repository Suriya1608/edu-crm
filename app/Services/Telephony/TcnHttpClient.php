<?php

namespace App\Services\Telephony;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TcnHttpClient
{
    private const RETRY_DELAYS_MS = [250, 800, 1800];
    private const RETRYABLE_STATUS_CODES = [408, 425, 429, 500, 502, 503, 504, 520, 522, 524, 529];
    private const CIRCUIT_OPEN_SECONDS = 30;

    public function post(
        string $scope,
        string $url,
        array $payload = [],
        ?string $token = null,
        bool $asForm = false
    ): HttpClientResponse {
        if ($this->isCircuitOpen($scope)) {
            return $this->makeSyntheticResponse(503, [
                'error' => 'TCN temporary circuit open. Please retry shortly.',
                'scope' => $scope,
            ]);
        }

        $attempts = count(self::RETRY_DELAYS_MS) + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $pending = Http::timeout(20)->acceptJson();
                if ($token) {
                    $pending = $pending->withToken($token);
                }
                if ($asForm) {
                    $pending = $pending->asForm();
                }

                $response = $pending->post($url, $payload);
                if ($response->successful()) {
                    $this->closeCircuit($scope);
                    return $response;
                }

                $status = $response->status();
                if ($this->isRetryableStatus($status) && $attempt < $attempts) {
                    usleep(self::RETRY_DELAYS_MS[$attempt - 1] * 1000);
                    continue;
                }

                if ($this->isRetryableStatus($status)) {
                    $this->openCircuit($scope, $status, $response->body());
                }
                return $response;
            } catch (\Throwable $e) {
                if ($attempt < $attempts) {
                    usleep(self::RETRY_DELAYS_MS[$attempt - 1] * 1000);
                    continue;
                }
                $this->openCircuit($scope, null, $e->getMessage());
                Log::error('TCN upstream request failed after retries', [
                    'scope' => $scope,
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
                return $this->makeSyntheticResponse(503, [
                    'error' => 'TCN upstream unavailable after retries.',
                    'scope' => $scope,
                ]);
            }
        }

        return $this->makeSyntheticResponse(503, ['error' => 'TCN request aborted unexpectedly.']);
    }

    private function circuitKey(string $scope): string
    {
        return 'tcn:circuit:' . $scope;
    }

    private function isCircuitOpen(string $scope): bool
    {
        return Cache::has($this->circuitKey($scope));
    }

    private function openCircuit(string $scope, ?int $status = null, ?string $body = null): void
    {
        Cache::put($this->circuitKey($scope), [
            'opened_at' => now()->toISOString(),
            'status'    => $status,
            'body'      => $body ? substr($body, 0, 500) : null,
        ], now()->addSeconds(self::CIRCUIT_OPEN_SECONDS));
    }

    private function closeCircuit(string $scope): void
    {
        Cache::forget($this->circuitKey($scope));
    }

    private function isRetryableStatus(int $status): bool
    {
        return in_array($status, self::RETRYABLE_STATUS_CODES, true);
    }

    private function makeSyntheticResponse(int $status, array $payload): HttpClientResponse
    {
        return new HttpClientResponse(
            new Psr7Response(
                $status,
                ['Content-Type' => 'application/json'],
                json_encode($payload)
            )
        );
    }
}

