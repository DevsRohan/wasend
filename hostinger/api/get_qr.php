<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$node = new NodeClient();
if (!$node->isConfigured()) json_error('node_not_configured', 503);

$res = $node->getQr();
if (empty($res['ok'])) {
    json_error($res['error'] ?? 'qr_unavailable', 503, ['detail' => $res]);
}
json_ok([
    'state' => $res['data']['state'] ?? ($res['state'] ?? 'unknown'),
    'qr'    => $res['data']['qr']    ?? ($res['qr'] ?? null),   // base64 data URL
]);
