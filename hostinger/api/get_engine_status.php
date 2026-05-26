<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$node = new NodeClient();
if (!$node->isConfigured()) {
    json_ok(['ok' => false, 'state' => 'not_configured']);
}
$res = $node->getStatus();
// Node /status returns flat: { ok:true, state:'ready', ready:bool, ... }
json_ok([
    'ok'    => !empty($res['ok']),
    'state' => $res['state'] ?? 'unknown',
    'ready' => (bool) ($res['ready'] ?? false),
    'info'  => $res,
]);
