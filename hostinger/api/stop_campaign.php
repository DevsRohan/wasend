<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$repo = new CampaignRepo();
$camp = $repo->getDefault();
if (!$camp) json_error('no_campaign', 404);

$repo->setStatus((int) $camp['id'], 'stopped');
wasend_log('info', 'campaign', 'stopped', ['id' => $camp['id']]);

json_ok(['status' => 'stopped', 'campaign_id' => (int) $camp['id']]);
