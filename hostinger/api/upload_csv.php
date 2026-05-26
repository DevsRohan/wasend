<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

if (!filter_var(wasend_setting('feature_csv_upload', '1'), FILTER_VALIDATE_BOOLEAN)) {
    json_error('feature_disabled', 403);
}
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    json_error('upload_failed', 400);
}

$tmp  = $_FILES['file']['tmp_name'];
$name = $_FILES['file']['name'];
$size = (int) $_FILES['file']['size'];

if ($size <= 0)              json_error('empty_file', 400);
if ($size > 30 * 1024 * 1024) json_error('file_too_large', 413); // 30 MB

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if ($ext !== 'csv') json_error('only_csv_allowed', 415);

// Move to uploads/csv with safe name
$dir = WASEND_UPLOADS . '/csv';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$safe = $dir . '/' . date('Ymd_His') . '_' . short_id(8) . '.csv';
if (!move_uploaded_file($tmp, $safe)) json_error('cannot_save_file', 500);

$parsed = CsvParser::parse($safe);
if (empty($parsed['rows'])) {
    json_error('no_valid_rows', 422, ['stats' => $parsed['stats']]);
}

$result = CsvParser::importToDb($parsed['rows']);

// Inline validate the first few leads synchronously so the user sees
// immediate results without waiting for cron / frontend pump. Bounded to
// keep request under ~15s. Frontend pumps the rest in 10-batches.
$inlineLimit = 3;
$pdo  = wasend_db();
$node = new NodeClient();
$inlineStats = ['validated' => 0, 'valid' => 0, 'invalid' => 0];

if ($node->isConfigured()) {
    try {
        $status = $node->getStatus();
        // Node /status returns flat: { ok:true, state:'ready', ready:bool, ... }
        $state  = (string) ($status['state'] ?? 'unknown');
        if (in_array(strtolower($state), ['ready','connected','authenticated'], true)) {
            $leadRepo = new LeadRepo();
            $stmt = $pdo->prepare("SELECT id, phone_number FROM leads WHERE whatsapp_status = 'pending' ORDER BY id ASC LIMIT :lim");
            $stmt->bindValue(':lim', $inlineLimit, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll() as $lead) {
                try {
                    $r = $node->checkNumber($lead['phone_number']);
                    if (!empty($r['ok'])) {
                        // Node /check-number returns flat: { ok:true, on_whatsapp:bool, jid:'...', phone:'...' }
                        $onWa = (bool) ($r['on_whatsapp'] ?? false);
                        $jid  = $r['jid'] ?? null;
                        if ($onWa) {
                            $leadRepo->setWhatsappStatus((int)$lead['id'], 'valid', $jid);
                            $inlineStats['valid']++;
                        } else {
                            $leadRepo->setWhatsappStatus((int)$lead['id'], 'not_on_whatsapp', null);
                            $inlineStats['invalid']++;
                        }
                        $inlineStats['validated']++;
                    }
                } catch (Throwable $e) { /* continue with next lead */ }
            }
        }
    } catch (Throwable $e) {
        wasend_log('warning', 'csv_import', 'inline_validate_failed', ['err' => $e->getMessage()]);
    }
}

// How many leads still need WhatsApp validation after inline batch?
$pendingValidate = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE whatsapp_status = 'pending'")->fetchColumn();

wasend_log('info', 'csv_import', 'completed', [
    'file'             => basename($safe),
    'inserted'         => $result['inserted'],
    'duplicates'       => $result['duplicates'],
    'total'            => $result['total'],
    'inline_validated' => $inlineStats['validated'],
]);

json_ok([
    'imported'         => $result['inserted'],
    'duplicates'       => $result['duplicates'],
    'parsed'           => count($parsed['rows']),
    'stats'            => $parsed['stats'],
    'file'             => basename($safe),
    'pending_validate' => $pendingValidate,
    'inline_validated' => $inlineStats,
    'auto_validate_hint' => 'Frontend should now pump api/trigger_validate_now.php in batches of 10 until pending_validate hits 0.',
]);
