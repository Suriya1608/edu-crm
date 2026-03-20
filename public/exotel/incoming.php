<?php
/**
 * Exotel Incoming Call Test Handler
 * URL: /exotel/incoming.php
 *
 * Returns a JSON SIP-routing response that tells Exotel to connect the
 * incoming call to a registered WebRTC browser client.
 *
 * Configure this URL in your Exotel dashboard under:
 *   Apps › [Your App] › Incoming Call Action URL → https://yourdomain.com/exotel/incoming.php
 */

declare(strict_types=1);

// ── No HTML output, ever ────────────────────────────────────────────
ini_set('display_errors', '0');
error_reporting(0);

// ── SIP target: the WebRTC agent registered with Exotel ────────────
// Format: sip:<exotel-sip-username>@<voip-domain>
// Replace these with values from Admin › Call Settings (or .env)
$SIP_USERNAME  = 'jayasurr9179f2a0';           // Exotel SIP username
$SIP_DOMAIN    = 'insighthcm5m.voip.exotel.com'; // Exotel VoIP domain
$LOG_FILE      = __DIR__ . '/incoming_log.txt';

// ── Collect all incoming request data ──────────────────────────────
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

    // Try JSON first, fall back to form-encoded
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $requestData['body'] = $decoded;
    } else {
        parse_str($rawBody, $formData);
        $requestData['body'] = is_array($formData) ? $formData : [];
    }
}

// Merge POST fields (for x-www-form-urlencoded from Exotel)
if (!empty($_POST)) {
    $requestData['body'] = array_merge($requestData['body'], $_POST);
}

// ── Optional: override SIP target from query/body (for dynamic routing) ─
$paramUser   = trim((string)($_GET['sip_user']   ?? $_POST['sip_user']   ?? ''));
$paramDomain = trim((string)($_GET['sip_domain'] ?? $_POST['sip_domain'] ?? ''));

if ($paramUser   !== '') $SIP_USERNAME = $paramUser;
if ($paramDomain !== '') $SIP_DOMAIN   = $paramDomain;

$sipTarget = 'sip:' . $SIP_USERNAME . '@' . $SIP_DOMAIN;

// ── Log request to file ─────────────────────────────────────────────
$logEntry  = str_repeat('-', 60) . PHP_EOL;
$logEntry .= json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
$logEntry .= 'SIP Target: ' . $sipTarget . PHP_EOL;

@file_put_contents($LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);

// ── Build JSON response ─────────────────────────────────────────────
$callerNumber = trim((string)(
    $requestData['body']['From']      ??
    $requestData['body']['from']      ??
    $requestData['body']['CallFrom']  ??
    $requestData['query']['From']     ??
    $requestData['query']['from']     ??
    ''
));

$callSid = trim((string)(
    $requestData['body']['CallSid']   ??
    $requestData['body']['call_sid']  ??
    $requestData['body']['Sid']       ??
    $requestData['query']['CallSid']  ??
    ''
));

// $response = [
//     'type'        => 'sip',
//     'to'          => $sipTarget,
//     'caller'      => $callerNumber ?: null,
//     'call_sid'    => $callSid      ?: null,
//     'sip_domain'  => $SIP_DOMAIN,
//     'sip_user'    => $SIP_USERNAME,
//     'handled_at'  => $requestData['timestamp'],
// ];
// $response = [
//     "connect" => [
//         "to" => $sipTarget
//     ]
// ];
$response = [
    "type" => "sip",
    "to"   => $sipTarget
];
// ── Send JSON response ──────────────────────────────────────────────
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');

// Flush any accidental output buffer before echoing JSON
if (ob_get_length()) {
    ob_clean();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
