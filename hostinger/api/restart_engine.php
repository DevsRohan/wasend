<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$node = new NodeClient();
if (!$node->isConfigured()) json_error('node_not_configured', 503);

$res = $node->restartSession();
wasend_log('warning', 'engine', 'restart_requested', ['result' => $res]);
json_ok(['result' => $res]);
