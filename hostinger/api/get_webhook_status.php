<?php
/**
 * get_webhook_status.php - Diagnostic for webhook health.
 */
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$pdo = wasend_db();

$secret = (string) wasend_setting('webhook_secret', '');
$secretLen = strlen($secret);
$secretLoaded = $secret !== '';

$rows = $pdo->query(
    "SELECT id, event_id, event_type, signature_ok, processed, error_message, received_at, processed_at
     FROM webhook_log ORDER BY id DESC LIMIT 20"
)->fetchAll();

$agg = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        SUM(signature_ok = 1)  AS sig_ok,
        SUM(signature_ok = 0)  AS sig_bad,
        SUM(processed = 1)     AS processed,
        SUM(error_message IS NOT NULL AND error_message <> '') AS errored
     FROM webhook_log
     WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)->fetch() ?: [];

$lastOk  = $pdo->query("SELECT received_at FROM webhook_log WHERE signature_ok = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
$lastBad = $pdo->query("SELECT received_at FROM webhook_log WHERE signature_ok = 0 ORDER BY id DESC LIMIT 1")->fetchColumn();

$hint = 'OK';
if (!$secretLoaded) {
    $hint = 'CRITICAL: webhook_secret decrypts to empty. Either it was never saved, OR APP_KEY in /config/.env changed after saving (decryption fails). Re-enter webhook_secret in Settings - Connection.';
} elseif ((int)($agg['sig_bad'] ?? 0) > 0 && (int)($agg['sig_ok'] ?? 0) === 0) {
    $hint = 'Signature mismatch: PHP webhook_secret differs from HF Space WEBHOOK_SECRET. They must match exactly (no trailing spaces). Re-paste both sides and Save.';
} elseif ((int)($agg['total'] ?? 0) === 0) {
    $hint = 'No webhooks received yet in last 24h. Verify HF Space env var WEBHOOK_URL points to ' . APP_URL . '/webhook.php';
} elseif ((int)($agg['sig_bad'] ?? 0) > 0) {
    $hint = 'Some webhooks failing signature. Check if you recently changed WEBHOOK_SECRET on either side without updating the other.';
}

json_ok([
    'secret_loaded'   => $secretLoaded,
    'secret_length'   => $secretLen,
    'app_key_set'     => defined('APP_KEY') && APP_KEY !== '' && APP_KEY !== 'change-me-32-bytes-min-secret-key!!',
    'webhook_url'     => APP_URL . '/webhook.php',
    'last_24h'        => [
        'total'         => (int) ($agg['total'] ?? 0),
        'signature_ok'  => (int) ($agg['sig_ok'] ?? 0),
        'signature_bad' => (int) ($agg['sig_bad'] ?? 0),
        'processed'     => (int) ($agg['processed'] ?? 0),
        'errored'       => (int) ($agg['errored'] ?? 0),
    ],
    'last_ok_at'      => $lastOk ?: null,
    'last_bad_at'     => $lastBad ?: null,
    'recent'          => $rows,
    'hint'            => $hint,
]);
