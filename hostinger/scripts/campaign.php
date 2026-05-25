<?php
/**
 * campaign.php - Cron-driven campaign runner.
 *
 * Behavior:
 *   - Runs every cron tick (recommended: every 1-2 minutes).
 *   - Sends at most ONE first-outreach message per tick.
 *   - Honors random delay 120-300s (or campaign-configured) between sends.
 *   - Honors working hours window.
 *   - Honors daily send limit.
 *   - Stops touching a lead immediately after they reply.
 *   - Persistent retry-tolerant: if Node send fails, marks failed and continues.
 *
 * Cron example (Hostinger):
 *   * * * * * /usr/bin/php /home/user/public_html/scripts/campaign.php >> /home/user/public_html/logs/campaign.log 2>&1
 */
declare(strict_types=1);

require_once __DIR__ . '/_cli_bootstrap.php';

$pdo = wasend_db();
$campaignRepo = new CampaignRepo();
$leadRepo     = new LeadRepo();
$msgRepo      = new MessageRepo();
$node         = new NodeClient();

$camp = $campaignRepo->getDefault();
if (!$camp) {
    cli_log('No default campaign found. Exiting.');
    exit(0);
}

$campaignId = (int) $camp['id'];

if ($camp['status'] !== 'running') {
    cli_log("Campaign #{$campaignId} status={$camp['status']} - skipping.");
    exit(0);
}

if (!filter_var(wasend_setting('campaign_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
    cli_log('Campaign master toggle is OFF.');
    exit(0);
}

// Reset daily counter if last_sent_at was a previous day
if (!empty($camp['last_sent_at'])) {
    if (date('Y-m-d', strtotime($camp['last_sent_at'])) !== date('Y-m-d')) {
        $campaignRepo->resetDailyCounter($campaignId);
        $camp['sent_today'] = 0;
        cli_log("Reset daily counter for campaign #$campaignId");
    }
}

// Working hours
$startHour = (int) wasend_setting('working_hours_start', 10);
$endHour   = (int) wasend_setting('working_hours_end', 20);
$nowHour   = (int) date('H');
if ($nowHour < $startHour || $nowHour >= $endHour) {
    cli_log("Outside working hours ($startHour-$endHour). Now=$nowHour. Skipping.");
    exit(0);
}

// Daily limit
$dailyLimit = (int) ($camp['daily_limit'] ?: wasend_setting('daily_send_limit', 80));
$sentToday  = (int) $camp['sent_today'];
if ($sentToday >= $dailyLimit) {
    cli_log("Daily limit reached ($sentToday/$dailyLimit). Skipping.");
    exit(0);
}

// Anti-ban pacing: only proceed if next_run_at <= NOW
$now = new DateTimeImmutable('now');
if (!empty($camp['next_run_at']) && new DateTimeImmutable($camp['next_run_at']) > $now) {
    $diff = strtotime($camp['next_run_at']) - time();
    cli_log("Pacing: next send in {$diff}s. Skipping.");
    exit(0);
}

if (!$node->isConfigured()) {
    cli_log('Node engine not configured. Skipping.');
    exit(0);
}

// Verify engine connected
$status = $node->getStatus();
$state  = (string) ($status['data']['state'] ?? ($status['state'] ?? 'unknown'));
if (!in_array(strtolower($state), ['ready','connected','authenticated'], true)) {
    cli_log("Engine not ready (state=$state). Skipping.");
    exit(0);
}

// Pick next eligible lead
$leads = $leadRepo->nextForCampaign($campaignId, 1);
if (!$leads) {
    cli_log('No eligible leads. Campaign idle.');
    exit(0);
}
$lead = $leads[0];
$leadId = (int) $lead['id'];

// Skip if lead already replied (race-safe)
if (in_array($lead['outreach_status'], ['replied','sent','delivered','read'], true)
    && filter_var(wasend_setting('skip_replied_leads', '1'), FILTER_VALIDATE_BOOLEAN)) {
    cli_log("Lead #$leadId already in state {$lead['outreach_status']} - skipping.");
    exit(0);
}

// Add to queue (idempotent)
$queueId = $campaignRepo->enqueue($campaignId, $leadId, date('Y-m-d H:i:s'));

// Build personalized context + AI message
$ownerName     = (string) wasend_setting('owner_name', 'Our team');
$ownerServices = (string) wasend_setting('owner_services', '');
$ctx  = Personalization::buildContext($lead, ['name' => $ownerName, 'services' => $ownerServices]);

$groq = new Groq();
$gen  = $groq->generateOutreach($ctx);
$text = (string) $gen['message'];

if ($text === '') {
    cli_log("Lead #$leadId: empty message generated. Skipping.");
    $campaignRepo->markQueueSkipped($queueId, 'empty_generated_message');
    exit(0);
}

// Attempt send
$jid = $lead['whatsapp_jid'] ?: ($lead['phone_number'] . '@c.us');
$res = $node->sendMessage($jid, $text, [
    'lead_id'  => $leadId,
    'campaign' => $campaignId,
    'source'   => 'campaign_first_outreach',
]);

if (!empty($res['ok'])) {
    $waId = $res['data']['wa_message_id'] ?? ($res['wa_message_id'] ?? null);
    $msgId = $msgRepo->insertOutbound($leadId, $text, $waId, true, 'system', [
        'campaign_id' => $campaignId,
        'ai_source'   => $gen['source'],
        'pitch_type'  => $ctx['pitch_type'],
        'language'    => $ctx['language'],
        'services'    => $ctx['services'],
    ]);
    $leadRepo->setOutreachStatus($leadId, 'sent', date('Y-m-d H:i:s'));
    $campaignRepo->markQueueSent($queueId, $msgId);
    $campaignRepo->incrementSent($campaignId);

    // Set next_run_at with random delay
    $delay = (int) random_int(
        (int) max(60, $camp['min_delay_seconds']),
        (int) max($camp['min_delay_seconds'], $camp['max_delay_seconds'])
    );
    $next = (new DateTimeImmutable("+{$delay} seconds"))->format('Y-m-d H:i:s');
    $campaignRepo->update($campaignId, ['next_run_at' => $next]);

    wasend_log('info', 'campaign', 'sent', [
        'lead_id'    => $leadId,
        'wa_id'      => $waId,
        'ai_source'  => $gen['source'],
        'next_run'   => $next,
        'sent_today' => $sentToday + 1,
    ]);
    cli_log("✓ Sent to lead #$leadId ({$lead['business_name']}). Next in {$delay}s. Today: " . ($sentToday + 1) . "/$dailyLimit");
} else {
    $err = (string) ($res['error'] ?? 'send_failed');
    $campaignRepo->markQueueFailed($queueId, $err);
    $campaignRepo->incrementFailed($campaignId);
    $leadRepo->setOutreachStatus($leadId, 'failed');

    // Pace anyway to avoid hammering on errors
    $delay = 90;
    $next = (new DateTimeImmutable("+{$delay} seconds"))->format('Y-m-d H:i:s');
    $campaignRepo->update($campaignId, ['next_run_at' => $next]);

    wasend_log('error', 'campaign', 'send_failed', ['lead_id' => $leadId, 'err' => $err]);
    cli_log("✗ Send failed for lead #$leadId: $err");
}

exit(0);
