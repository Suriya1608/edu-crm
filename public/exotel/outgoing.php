<?php
/**
 * Exotel Outgoing Call Handler
 * URL: /exotel/outgoing.php
 *
 * Returns a JSON response that instructs Exotel to connect the outgoing call
 * to the destination number received in the request.
 *
 * Configure this URL in your Exotel dashboard under:
 *   Apps › [Your Passthru App] › Outgoing Call Action URL
 *   → https://yourdomain.com/exotel/outgoing.php
 */

declare(strict_types=1);

// ── Suppress all HTML error output ──────────────────────────────────
ini_set('display_errors', '0');
error_reporting(0);

$LOG_FILE = __DIR__ . '/outgoing_log.txt';

// ── Collect all incoming request data ───────────────────────────────
$requestData = [
    'timestamp'  => date('Y-m-d H:i:s'),
    'method'     => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'remote_ip'  => $_SERVER['REMOTE_ADDR']     ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'query'      => $_GET,
    'body'       => [],
    'raw_body'   => '',
];

$rawBody = file_get_contents('php://input') ?: '';

if ($rawBody !== '') {
    $requestData['raw_body'] = $rawBody;

    // Try JSON first, then fall back to form-encoded
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $requestData['body'] = $decoded;
    } else {
        parse_str($rawBody, $formData);
        $requestData['body'] = is_array($formData) ? $formData : [];
    }
}

// Merge POST fields (Exotel typically sends x-www-form-urlencoded)
if (!empty($_POST)) {
    $requestData['body'] = array_merge($requestData['body'], $_POST);
}

// ── Extract destination number from To parameter ─────────────────────
// Exotel sends the destination in 'To' (GET or POST)
$destination = trim((string)(
    $requestData['body']['To']   ??
    $requestData['body']['to']   ??
    $requestData['query']['To']  ??
    $requestData['query']['to']  ??
    ''
));

// Also capture useful call metadata for logging
$callSid = trim((string)(
    $requestData['body']['CallSid']  ??
    $requestData['body']['call_sid'] ??
    $requestData['query']['CallSid'] ??
    ''
));

$callFrom = trim((string)(
    $requestData['body']['From']     ??
    $requestData['body']['CallFrom'] ??
    $requestData['query']['From']    ??
    $requestData['query']['CallFrom'] ??
    ''
));

// ── Log request to file ──────────────────────────────────────────────
$logEntry  = str_repeat('-', 60) . PHP_EOL;
$logEntry .= json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
$logEntry .= 'Destination: ' . ($destination ?: '(missing)') . PHP_EOL;

@file_put_contents($LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);

// ── Send JSON response ───────────────────────────────────────────────
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');

// Clean any accidental buffered output before echoing JSON
if (ob_get_length()) {
    ob_clean();
}

// ── Build response ───────────────────────────────────────────────────
if ($destination === '') {
    // Missing destination — return error so Exotel can handle gracefully
    echo json_encode([
        'error'   => true,
        'message' => 'Missing destination number. Expected "To" parameter in request.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Instruct Exotel to connect the call to the destination number
echo json_encode([
    'connect' => [
        'to' => $destination,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
