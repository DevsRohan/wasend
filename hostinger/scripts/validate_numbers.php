<?php
/**
 * validate_numbers.php - Validates pending leads against WhatsApp via Node engine.
 *
 * Behavior:
 *   - Picks N pending leads (default 30 per run).
 *   - Calls /check-number for each.
 *   - Marks valid/not_on_whatsapp/failed.
 *   - Adds small jitter between checks to avoid burst patterns.
 *
 * Cron example (every 5 min):
 *   *\/5 * * * * /usr/bin/php /home/user/public_html/scripts/validate_numbers.php
 */
declare(strict_types=1);
require_once __DIR__ . '/_cli_bootstrap.php';

$batchSize = (int) ($argv[1] ?? 30);

$pdo = wasend_db();
$leadRepo = new LeadRepo();
$node = new NodeClient();

if (!$node->isConfigured()) {
    cli_log('Node engine not configured. Exiting.');
    exit(0);
}

// Verify engine connected
$status = $node->getStatus();
$state  = (string) ($status['data']['state'] ?? ($status['state'] ?? 'unknown'));
if (!in_array(strtolower($state), ['ready','connected','authenticated'], true)) {
    cli_log("Engine not ready (state=$state). Exiting.");
    exit(0);
}

$stmt = $pdo->prepare("SELECT id, phone_number FROM leads WHERE whatsapp_status = 'pending' ORDER BY id ASC LIMIT :lim");
$stmt->bindValue(':lim', $batchSize, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

if (!$leads) {
    cli_log('No pending leads to validate.');
    exit(0);
}

cli_log('Validating ' . count($leads) . ' leads...');

$valid = 0; $invalid = 0; $failed = 0;
foreach ($leads as $lead) {
    $res = $node->checkNumber($lead['phone_number']);
    if (!empty($res['ok'])) {
        $onWa = (bool) ($res['data']['on_whatsapp'] ?? ($res['on_whatsapp'] ?? false));
        $jid  = $res['data']['jid'] ?? ($res['jid'] ?? null);
        if ($onWa) {
            $leadRepo->setWhatsappStatus((int) $lead['id'], 'valid', $jid);
            $valid++;
        } else {
            $leadRepo->setWhatsappStatus((int) $lead['id'], 'not_on_whatsapp', null);
            $invalid++;
        }
    } else {
        $leadRepo->setWhatsappStatus((int) $lead['id'], 'failed', null);
        $failed++;
    }
    // jitter 2-6s between checks
    usleep(random_int(2000000, 6000000));
}

wasend_log('info', 'validate_numbers', 'batch_done', compact('valid','invalid','failed'));
cli_log("Done. valid=$valid invalid=$invalid failed=$failed");
exit(0);
