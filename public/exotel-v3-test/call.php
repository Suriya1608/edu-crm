<?php

declare(strict_types=1);

header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(0);
ob_start();

$EXOTEL_API_KEY = 'fe2bcdc200314d2854dbfd08811fe29d90971009e13dca78';
$EXOTEL_API_TOKEN = '492a5dc3e46d115806ad4217c2a0e993e6a39cf8026d0161';
$EXOTEL_SID = 'insighthcm5m';
$EXOTEL_CALLER_ID = '+914469173757';
$STATUS_CALLBACK_URL = 'https://smashable-pricilla-sacramentally.ngrok-free.dev/exotel/webhook';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Method not allowed. Use POST.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonResponse(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (headers_sent() === false) {
        header('Content-Type: application/json');
        http_response_code(500);
    }

    if (ob_get_length()) {
        ob_clean();
    }

    echo json_encode([
        'ok' => false,
        'message' => 'Fatal PHP error while processing request.',
        'error' => $error['message'] ?? 'Unknown fatal error',
        'file' => $error['file'] ?? null,
        'line' => $error['line'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
});

function getJsonInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        return $decoded;
    }

    return $_POST;
}

function normalizeIndianPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        return '+91' . substr($digits, 1);
    }

    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        return '+' . $digits;
    }

    if (preg_match('/^\+\d{10,15}$/', $phone)) {
        return $phone;
    }

    return '';
}

try {
    $input = getJsonInput();
    $rawPhone = trim((string) ($input['phone'] ?? ''));
    $phone = normalizeIndianPhone($rawPhone);

    if ($rawPhone === '') {
        jsonResponse([
            'ok' => false,
            'message' => 'Phone number is required.',
        ], 422);
    }

    if ($EXOTEL_SID === '' || $EXOTEL_SID === 'YOUR_ACCOUNT_SID') {
        jsonResponse([
            'ok' => false,
            'message' => 'Exotel SID is missing. Set $EXOTEL_SID at the top of call.php.',
        ], 500);
    }

    if ($EXOTEL_API_KEY === '' || $EXOTEL_API_KEY === 'YOUR_API_KEY') {
        jsonResponse([
            'ok' => false,
            'message' => 'Exotel API key is missing. Set $EXOTEL_API_KEY at the top of call.php.',
        ], 500);
    }

    if ($EXOTEL_API_TOKEN === '' || $EXOTEL_API_TOKEN === 'YOUR_API_TOKEN') {
        jsonResponse([
            'ok' => false,
            'message' => 'Exotel API token is missing. Set $EXOTEL_API_TOKEN at the top of call.php.',
        ], 500);
    }

    if ($EXOTEL_CALLER_ID === '' || $EXOTEL_CALLER_ID === '+91XXXXXXXXXX') {
        jsonResponse([
            'ok' => false,
            'message' => 'Exotel caller ID is missing. Set $EXOTEL_CALLER_ID at the top of call.php.',
        ], 500);
    }

    if ($phone === '') {
        jsonResponse([
            'ok' => false,
            'message' => 'Invalid phone number. Enter a valid Indian number.',
            'input' => $rawPhone,
        ], 422);
    }

    $normalizedCallerId = normalizeIndianPhone($EXOTEL_CALLER_ID);

    if ($normalizedCallerId === '') {
        jsonResponse([
            'ok' => false,
            'message' => 'Exotel caller ID must be in valid E.164 format.',
            'caller_id' => $EXOTEL_CALLER_ID,
        ], 500);
    }

    $url = "https://ccm-api.in.exotel.com/v3/accounts/$EXOTEL_SID/calls";

    if (stripos($url, 'https://ccm-api.in.exotel.com/') !== 0) {
        jsonResponse([
            'ok' => false,
            'message' => 'Exotel API URL is invalid.',
            'api_url' => $url,
        ], 500);
    }

    // $data = [
    //     'from' => $normalizedCallerId,
    //     'to' => $phone,
    //     'caller_id' => $normalizedCallerId,
    //     'status_callback' => $STATUS_CALLBACK_URL,
    //     'status_callback_content_type' => 'application/json',
    // ];
    $data = [
        "from" => [
            "type" => "number",
            "number" => $EXOTEL_CALLER_ID
        ],
        "to" => [
            "type" => "number",
            "number" => $phone
        ],
        "caller_id" => $EXOTEL_CALLER_ID,
        "status_callback" => $STATUS_CALLBACK_URL,
        "status_callback_content_type" => "application/json"
    ];

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, "$EXOTEL_API_KEY:$EXOTEL_API_TOKEN");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decodedResponse = json_decode((string) $response, true);

    if ($curlErrno !== 0) {
        jsonResponse([
            'ok' => false,
            'message' => 'cURL request to Exotel failed.',
            'data' => [
                'error' => $curlError,
                'curl_errno' => $curlErrno,
                'request_payload' => $data,
                'api_url' => $url,
                'raw_response' => $response,
            ],
        ], 500);
    }

    $ok = $httpCode >= 200 && $httpCode < 300;

    jsonResponse([
        'ok' => $ok,
        'message' => $ok ? 'Call request sent to Exotel.' : 'Exotel returned an error response.',
        'data' => [
            'request_payload' => $data,
            'api_url' => $url,
            'http_status' => $httpCode,
            'raw_response' => $response,
            'response' => $decodedResponse,
        ],
    ], $ok ? 200 : max(400, $httpCode ?: 500));
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'message' => 'Unexpected server error while calling Exotel.',
        'data' => [
            'error' => $e->getMessage(),
            'trace_hint' => $e->getFile() . ':' . $e->getLine(),
        ],
    ], 500);
}
