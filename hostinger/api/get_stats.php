<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$leadRepo     = new LeadRepo();
$campaignRepo = new CampaignRepo();
$nodeClient   = new NodeClient();

$stats    = $leadRepo->stats();
$campaign = $campaignRepo->getDefault();
$qstate   = $campaign ? $campaignRepo->queueState((int) $campaign['id']) : ['pending'=>0,'sent'=>0,'failed'=>0,'skipped'=>0,'blocked'=>0,'total'=>0];

$engine = ['ok' => false, 'state' => 'unknown'];
try {
    $health = $nodeClient->getStatus();
    if (!empty($health['ok'])) {
        // Node /status returns: { ok:true, state:'ready', ready:bool, ... }
        // All fields are at top level (no 'data' wrapper for this endpoint)
        $engine = [
            'ok'    => true,
            'state' => $health['state'] ?? 'unknown',
            'ready' => (bool) ($health['ready'] ?? false),
        ];
    }
} catch (Throwable $e) {
    // ignore
}

json_ok([
    'stats'    => $stats,
    'campaign' => $campaign ? [
        'id'                => (int) $campaign['id'],
        'name'              => $campaign['name'],
        'status'            => $campaign['status'],
        'daily_limit'       => (int) $campaign['daily_limit'],
        'sent_today'        => (int) $campaign['sent_today'],
        'min_delay_seconds' => (int) $campaign['min_delay_seconds'],
        'max_delay_seconds' => (int) $campaign['max_delay_seconds'],
        'last_sent_at'      => $campaign['last_sent_at'],
        'next_run_at'       => $campaign['next_run_at'],
    ] : null,
    'queue'    => $qstate,
    'engine'   => $engine,
    'now'      => date('c'),
]);
