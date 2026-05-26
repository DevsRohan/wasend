<?php
/**
 * trigger_campaign_now.php - Manually run ONE campaign tick (sends 1 message).
 *
 * Mirrors scripts/campaign.php logic so behavior is identical whether
 * triggered via cron or manually. Anti-ban pacing, daily cap, working hours,
 * and reply-block rules are all respected.
 */
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

@set_time_limit(60);

$body  = read_json_input();
$force = !empty($body['force']);

$pdo          = wasend_db();
$campaignRepo = new CampaignRepo();
$leadRepo     = new LeadRepo();
$msgRepo      = new MessageRepo();
$node         = new NodeClient();

$camp = $campaignRepo->getDefault();
if (!$camp) json_error('no_campaign', 404);

$campaignId = (int) $camp['id'];

if ($camp['status'] !== 'running' && !$force) {
    json_error('campaign_not_running', 400, ['status' => $camp['status'], 'hint' => 'Click Start first.']);
}

if (!filter_var(wasend_setting('campaign_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
    json_error('campaign_master_off', 400);
}

if (!empty($camp['last_sent_at']) && date('Y-m-d', strtotime($camp['last_sent_at'])) !== date('Y-m-d')) {
    $campaignRepo->resetDailyCounter($campaignId);
    $camp['sent_today'] = 0;
}

$dailyLimit = (int) ($camp['daily_limit'] ?: wasend_setting('daily_send_limit', 80));
if ((int) $camp['sent_today'] >= $dailyLimit) {
    json_error('daily_limit_reached', 400, ['sent_today' => (int) $camp['sent_today'], 'limit' => $dailyLimit]);
}

if (!$force) {
    $startHour = (int) wasend_setting('working_hours_start', 10);
    $endHour   = (int) wasend_setting('working_hours_end', 20);
    $nowHour   = (int) date('H');
    if ($nowHour < $startHour || $nowHour >= $endHour) {
        json_error('outside_working_hours', 400, ['now_hour' => $nowHour, 'window' => "$startHour-$endHour"]);
    }
}

if (!$force && !empty($camp['next_run_at'])) {
    $next = strtotime($camp['next_run_at']);
    if ($next > time()) {
        json_error('pacing_active', 400, ['next_in_seconds' => $next - time(), 'next_run_at' => $camp['next_run_at']]);
    }
}

if (!$node->isConfigured()) json_error('node_not_configured', 503);

$status = $node->getStatus();
$state  = (string) ($status['state'] ?? 'unknown');
if (!in_array(strtolower($state), ['ready','connected','authenticated'], true)) {
    json_error('engine_not_ready', 503, ['state' => $state]);
}

$leads = $leadRepo->nextForCampaign($campaignId, 1);
if (!$leads) {
    json_error('no_eligible_leads', 404, [
        'hint' => 'No leads with whatsapp_status=valid + outreach_status=pending. Try "Validate Now" first.',
    ]);
}
$lead   = $leads[0];
$leadId = (int) $lead['id'];

$queueId = $campaignRepo->enqueue($campaignId, $leadId, date('Y-m-d H:i:s'));

$ownerName     = (string) wasend_setting('owner_name', 'Our team');
$ownerServices = (string) wasend_setting('owner_services', '');
$ctx  = Personalization::buildContext($lead, ['name' => $ownerName, 'services' => $ownerServices]);
$groq = new Groq();
$gen  = $groq->generateOutreach($ctx);
$text = (string) $gen['message'];

if ($text === '') {
    $campaignRepo->markQueueSkipped($queueId, 'empty_generated_message');
    json_error('empty_message', 500);
}

$jid = $lead['whatsapp_jid'] ?: ($lead['phone_number'] . '@c.us');
$res = $node->sendMessage($jid, $text, [
    'lead_id'  => $leadId,
    'campaign' => $campaignId,
    'source'   => 'manual_trigger',
]);

if (empty($res['ok'])) {
    $err = (string) ($res['error'] ?? 'send_failed');
    $campaignRepo->markQueueFailed($queueId, $err);
    $campaignRepo->incrementFailed($campaignId);
    $leadRepo->setOutreachStatus($leadId, 'failed');
    wasend_log('error', 'manual_trigger_campaign', 'send_failed', ['lead_id' => $leadId, 'err' => $err]);
    json_error('send_failed', 502, ['detail' => $res, 'lead_id' => $leadId]);
}

$waId  = $res['wa_message_id'] ?? null;
$msgId = $msgRepo->insertOutbound($leadId, $text, $waId, true, 'system', [
    'campaign_id' => $campaignId,
    'ai_source'   => $gen['source'],
    'pitch_type'  => $ctx['pitch_type'],
    'language'    => $ctx['language'],
    'services'    => $ctx['services'],
    'trigger'     => 'manual',
]);
$leadRepo->setOutreachStatus($leadId, 'sent', date('Y-m-d H:i:s'));
$campaignRepo->markQueueSent($queueId, $msgId);
$campaignRepo->incrementSent($campaignId);

$delay = (int) random_int(
    (int) max(60, $camp['min_delay_seconds']),
    (int) max($camp['min_delay_seconds'], $camp['max_delay_seconds'])
);
$nextAt = (new DateTimeImmutable("+{$delay} seconds"))->format('Y-m-d H:i:s');
$campaignRepo->update($campaignId, ['next_run_at' => $nextAt]);

wasend_log('info', 'manual_trigger_campaign', 'sent', [
    'lead_id'   => $leadId,
    'wa_id'     => $waId,
    'next_in_s' => $delay,
    'ai_source' => $gen['source'],
]);

json_ok([
    'lead_id'        => $leadId,
    'business_name'  => $lead['business_name'],
    'wa_message_id'  => $waId,
    'message'        => $text,
    'ai_source'      => $gen['source'],
    'next_in_seconds'=> $delay,
    'next_run_at'    => $nextAt,
]);
