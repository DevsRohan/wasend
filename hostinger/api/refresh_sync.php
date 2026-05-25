<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

// Returns latest stats + recent activity for periodic frontend polling fallback.
$leadRepo  = new LeadRepo();
$msgRepo   = new MessageRepo();
$campaignRepo = new CampaignRepo();

$stats    = $leadRepo->stats();
$activity = $msgRepo->recentActivity(20);
$campaign = $campaignRepo->getDefault();

json_ok([
    'stats'    => $stats,
    'activity' => $activity,
    'campaign' => $campaign,
    'now'      => date('c'),
]);
