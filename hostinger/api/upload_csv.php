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

// How many leads still need WhatsApp validation?
$pdo = wasend_db();
$pendingValidate = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE whatsapp_status = 'pending'")->fetchColumn();

wasend_log('info', 'csv_import', 'completed', [
    'file'     => basename($safe),
    'inserted' => $result['inserted'],
    'duplicates' => $result['duplicates'],
    'total'    => $result['total'],
]);

json_ok([
    'imported'         => $result['inserted'],
    'duplicates'       => $result['duplicates'],
    'parsed'           => count($parsed['rows']),
    'stats'            => $parsed['stats'],
    'file'             => basename($safe),
    'pending_validate' => $pendingValidate,
    'auto_validate_hint' => 'Frontend should now pump api/trigger_validate_now.php in batches of 10 until pending_validate hits 0.',
]);
