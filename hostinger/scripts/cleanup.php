<?php
/**
 * cleanup.php - Periodic cleanup of old logs, expired tokens, old webhook entries.
 *
 * Cron example (daily at 3am):
 *   0 3 * * * /usr/bin/php /home/user/public_html/scripts/cleanup.php
 */
declare(strict_types=1);
require_once __DIR__ . '/_cli_bootstrap.php';

$pdo = wasend_db();

// Activity log: keep 30 days
$stmt = $pdo->exec("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$activityRows = $pdo->prepare("SELECT ROW_COUNT() AS rc")->fetch();
$activityDeleted = (int) ($activityRows['rc'] ?? 0);

// Webhook log: keep 14 days
$pdo->exec("DELETE FROM webhook_log WHERE received_at < DATE_SUB(NOW(), INTERVAL 14 DAY)");

// Expired socket tokens
$pdo->exec("DELETE FROM socket_tokens WHERE expires_at < NOW()");

// File logs rotate (truncate if > 25 MB)
foreach (['app.log', 'php_errors.log', 'campaign.log', 'webhook.log'] as $f) {
    $p = WASEND_LOGS . '/' . $f;
    if (is_file($p) && filesize($p) > 25 * 1024 * 1024) {
        @rename($p, $p . '.' . date('Ymd_His') . '.bak');
        @file_put_contents($p, "[rotated " . date('c') . "]\n");
    }
}

wasend_log('info', 'cleanup', 'done', ['activity_deleted' => $activityDeleted]);
cli_log("Cleanup done. Activity rows deleted: $activityDeleted");
exit(0);
