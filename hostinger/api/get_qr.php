<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$node = new NodeClient();
if (!$node->isConfigured()) json_error('node_not_configured', 503);

$res = $node->getQr();
if (empty($res['ok'])) {
    json_error($res['error'] ?? 'qr_unavailable', 503, ['detail' => $res]);
}
// Node /qr returns flat: { ok:true, state:'qr_required', qr:'data:image/png;base64,...' }
json_ok([
    'state' => $res['state'] ?? 'unknown',
    'qr'    => $res['qr']    ?? null,
]);
