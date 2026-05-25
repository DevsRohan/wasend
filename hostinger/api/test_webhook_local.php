<?php
/**
 * test_webhook_local.php - Self-test webhook signature verification.
 *
 * Generates a fake engine_state payload, signs it with the locally-stored
 * webhook_secret, and POSTs it to our own webhook.php. If this returns
 * ok=true, the secret is correctly configured on the PHP side. Then the
 * only remaining variable is whether HF's WEBHOOK_SECRET matches.
 */
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$secret = (string) wasend_setting('webhook_secret', '');
if ($secret === '') {
    json_error('webhook_secret_empty', 400, [
        'hint' => 'webhook_secret in Settings → Connection is empty (or APP_KEY in /config/.env differs from when it was saved). Re-enter the secret.',
    ]);
}

$payload = [
    'type'  => 'engine_state',
    'state' => 'self_test',
    'at'    => date('c'),
    '_test' => true,
];
$body    = json_encode($payload, JSON_UNESCAPED_UNICODE);
$sig     = 'sha256=' . hash_hmac('sha256', $body, $secret);
$eventId = 'self-test-' . bin2hex(random_bytes(8));
$url     = (defined('APP_URL') && APP_URL ? APP_URL : (
    ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST']
)) . '/webhook.php';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Webhook-Signature: ' . $sig,
        'X-Webhook-Event-Id: '  . $eventId,
        'X-Webhook-Source: self-test',
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

$decoded = json_decode((string) $resp, true);

json_ok([
    'url'         => $url,
    'http_status' => $code,
    'curl_error'  => $err ?: null,
    'response'    => $decoded ?: $resp,
    'verdict'     => ($code >= 200 && $code < 300)
        ? 'PASS - PHP-side signature verification works. If HF webhooks still fail, the secrets between PHP and HF differ.'
        : 'FAIL - webhook.php rejected our self-signed payload. APP_KEY may have changed since webhook_secret was saved. Re-enter webhook_secret and retry.',
]);
