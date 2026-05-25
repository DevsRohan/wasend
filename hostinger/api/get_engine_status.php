<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$node = new NodeClient();
if (!$node->isConfigured()) {
    json_ok(['ok' => false, 'state' => 'not_configured']);
}
$res = $node->getStatus();
json_ok([
    'ok'    => !empty($res['ok']),
    'state' => $res['data']['state'] ?? ($res['state'] ?? 'unknown'),
    'info'  => $res['data'] ?? $res,
]);
