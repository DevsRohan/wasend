<?php
/**
 * retry_failed.php - Re-queues failed messages (within retry limit).
 *
 * Cron example (every 15 min):
 *   *\/15 * * * * /usr/bin/php /home/user/public_html/scripts/retry_failed.php
 */
declare(strict_types=1);
require_once __DIR__ . '/_cli_bootstrap.php';

$pdo = wasend_db();
$maxAttempts = (int) wasend_setting('retry_max_attempts', 3);

// Reset failed queue items below max attempts to pending
$stmt = $pdo->prepare(
    "UPDATE campaign_queue
     SET status = 'pending', last_error = CONCAT(IFNULL(last_error,''), ' | retry_at=', NOW())
     WHERE status = 'failed' AND attempts < :max"
);
$stmt->bindValue(':max', $maxAttempts, PDO::PARAM_INT);
$stmt->execute();
$q = $stmt->rowCount();

// Reset lead.outreach_status from failed to pending where lead has not replied
$stmt2 = $pdo->prepare(
    "UPDATE leads SET outreach_status = 'pending'
     WHERE outreach_status = 'failed'
       AND id IN (
           SELECT lead_id FROM (
              SELECT lead_id FROM campaign_queue WHERE status = 'pending' AND attempts < :max
           ) tmp
       )"
);
$stmt2->bindValue(':max', $maxAttempts, PDO::PARAM_INT);
$stmt2->execute();
$l = $stmt2->rowCount();

wasend_log('info', 'retry_failed', 'completed', ['queue' => $q, 'leads' => $l]);
cli_log("Retry queued: $q queue items, $l leads.");
exit(0);
