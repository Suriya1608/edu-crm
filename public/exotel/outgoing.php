<?php
/**
 * Exotel Outgoing Call Handler (Passthru)
 * URL: /exotel/outgoing.php
 */

declare(strict_types=1);

// ─────────────────────────────────────────
// CONFIG
// ─────────────────────────────────────────
$LOG_FILE = __DIR__ . '/outgoing_log.txt';

// ─────────────────────────────────────────
// DISABLE HTML ERRORS (IMPORTANT)
// ─────────────────────────────────────────
ini_set('display_errors', '0');
error_reporting(0);

// ─────────────────────────────────────────
// COLLECT REQUEST DATA
// ─────────────────────────────────────────
$rawBody = file_get_contents('php://input') ?: '';

$data = [];

// Parse JSON OR form-data
if (!empty($rawBody)) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $data = $decoded;
    } else {
        parse_str($rawBody, $data);
    }
}

// Merge POST + GET
$data = array_merge($data, $_POST, $_GET);

// ─────────────────────────────────────────
// EXTRACT VALUES
// ─────────────────────────────────────────
$to = trim((string)($data['To'] ?? $data['to'] ?? ''));
$from = trim((string)($data['From'] ?? $data['CallFrom'] ?? ''));
$callSid = trim((string)($data['CallSid'] ?? ''));

// ─────────────────────────────────────────
// LOG REQUEST
// ─────────────────────────────────────────
$log = [
    'time' => date('Y-m-d H:i:s'),
    'to' => $to,
    'from' => $from,
    'call_sid' => $callSid,
    'data' => $data
];

file_put_contents($LOG_FILE, json_encode($log, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// ─────────────────────────────────────────
// RESPONSE (XML REQUIRED BY EXOTEL)
// ─────────────────────────────────────────
header('Content-Type: text/xml');

// Clean any output buffer
if (ob_get_length()) {
    ob_clean();
}

// If number missing → safe fallback
if ($to === '') {
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';
    echo '<Say>Invalid number</Say>';
    echo '</Response>';
    exit;
}

// ─────────────────────────────────────────
// SUCCESS RESPONSE
// ─────────────────────────────────────────
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<Response>
    <Dial>
        <Number><?php echo htmlspecialchars($to); ?></Number>
    </Dial>
</Response>