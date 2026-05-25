<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$repo = new CampaignRepo();
$camp = $repo->getDefault();
if (!$camp) json_error('no_campaign', 404);

$repo->setStatus((int) $camp['id'], 'running');
wasend_log('info', 'campaign', 'resumed', ['id' => $camp['id']]);

json_ok(['status' => 'running', 'campaign_id' => (int) $camp['id']]);
