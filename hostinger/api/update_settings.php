<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body = read_json_input();
if (empty($body['settings']) || !is_array($body['settings'])) {
    json_error('settings_payload_required', 422);
}

$repo  = new SettingsRepo();
$count = $repo->bulkSet($body['settings']);

wasend_log('info', 'settings', 'updated', ['count' => $count, 'keys' => array_keys($body['settings'])]);

json_ok(['updated' => $count]);
