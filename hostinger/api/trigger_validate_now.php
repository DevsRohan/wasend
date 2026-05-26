<?php
/**
 * trigger_validate_now.php - Manually validate a small batch of pending leads.
 * Same logic as scripts/validate_numbers.php but exposed as an HTTP endpoint
 * so users without cron can trigger validation from the dashboard.
 */
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

@set_time_limit(60);
@ini_set('max_execution_time', '60');

$body  = read_json_input();
$batch = max(1, min(15, (int) ($body['batch'] ?? 10)));

$pdo  = wasend_db();
$repo = new LeadRepo();
$node = new NodeClient();

if (!$node->isConfigured()) {
    json_error('node_not_configured', 503);
}

$status = $node->getStatus();
// Node /status returns flat: { ok:true, state:'ready', ready:bool, ... }
$state  = (string) ($status['state'] ?? 'unknown');
if (!in_array(strtolower($state), ['ready','connected','authenticated'], true)) {
    json_error('engine_not_ready', 503, ['state' => $state]);
}

$stmt = $pdo->prepare("SELECT id, phone_number FROM leads WHERE whatsapp_status = 'pending' ORDER BY id ASC LIMIT :lim");
$stmt->bindValue(':lim', $batch, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

if (!$leads) {
    json_ok(['validated' => 0, 'message' => 'No pending leads to validate.', 'remaining' => 0]);
}

$valid = 0; $invalid = 0; $failed = 0;
foreach ($leads as $lead) {
    $res = $node->checkNumber($lead['phone_number']);
    if (!empty($res['ok'])) {
        // Node /check-number returns flat: { ok:true, on_whatsapp:bool, jid:'...', phone:'...' }
        $onWa = (bool) ($res['on_whatsapp'] ?? false);
        $jid  = $res['jid'] ?? null;
        if ($onWa) { $repo->setWhatsappStatus((int)$lead['id'], 'valid', $jid); $valid++; }
        else       { $repo->setWhatsappStatus((int)$lead['id'], 'not_on_whatsapp', null); $invalid++; }
    } else {
        $repo->setWhatsappStatus((int)$lead['id'], 'failed', null);
        $failed++;
    }
    usleep(random_int(1_000_000, 2_000_000));
}

wasend_log('info', 'manual_validate', 'batch_done', compact('valid','invalid','failed','batch'));

json_ok([
    'validated' => count($leads),
    'valid'     => $valid,
    'invalid'   => $invalid,
    'failed'    => $failed,
    'remaining' => (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE whatsapp_status = 'pending'")->fetchColumn(),
]);
