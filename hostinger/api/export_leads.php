<?php
require_once __DIR__ . '/_bootstrap.php';
auth_check(false);

if (!filter_var(wasend_setting('feature_export', '1'), FILTER_VALIDATE_BOOLEAN)) {
    http_response_code(403);
    echo 'export_disabled';
    exit;
}

$pdo = wasend_db();
$rows = $pdo->query('SELECT id, business_name, phone_number, locality, city, state,
                            website_url, website_status, rating, review_count,
                            whatsapp_status, outreach_status, pitch_type,
                            language_preference, tags, last_contacted_at, last_reply_at,
                            created_at FROM leads ORDER BY id ASC')->fetchAll();

$filename = 'wasend_leads_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
$headers = array_keys($rows[0] ?? [
    'id'=>'','business_name'=>'','phone_number'=>'','locality'=>'','city'=>'','state'=>'',
    'website_url'=>'','website_status'=>'','rating'=>'','review_count'=>'',
    'whatsapp_status'=>'','outreach_status'=>'','pitch_type'=>'',
    'language_preference'=>'','tags'=>'','last_contacted_at'=>'','last_reply_at'=>'','created_at'=>''
]);
fputcsv($out, $headers);
foreach ($rows as $r) fputcsv($out, $r);
fclose($out);
exit;
