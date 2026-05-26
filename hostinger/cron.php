<?php
/**
 * cron.php - Self-driving cron endpoint called by HF Engine every 60s.
 *
 * Auth: HMAC-SHA256 over raw body using webhook_secret (same as webhook.php).
 *
 * Each tick runs (small + bounded so we stay under 50s timeout):
 *   1. Validate up to N pending leads via Node /check-number
 *   2. Run one campaign send (if eligible, respects all anti-ban rules)
 *   3. Light cleanup (expired socket tokens)
 *
 * This makes the platform self-driving on Hostinger shared hosting without
 * needing a real cron job - HF's always-on container does the scheduling.
 */
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

spl_autoload_register(function ($class) {
    $map = [
        'LeadRepo'        => '/lead_repo.php',
        'MessageRepo'     => '/message_repo.php',
        'CampaignRepo'    => '/campaign_repo.php',
        'SettingsRepo'    => '/settings_repo.php',
        'Personalization' => '/personalization.php',
        'Groq'            => '/groq.php',
        'NodeClient'      => '/node_client.php',
    ];
    if (isset($map[$class])) require_once WASEND_INCLUDES . $map[$class];
});

header('Content-Type: application/json');
@set_time_limit(55);

$rawBody = file_get_contents('php://input') ?: '';
$sig     = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$secret  = (string) wasend_setting('webhook_secret', '');

if (!auth_verify_webhook($rawBody, $sig, $secret)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'bad_signature']);
    wasend_log('warning', 'cron', 'bad_signature', [
        'sig_present'    => (bool) $sig,
        'secret_length'  => strlen($secret),
        'body_length'    => strlen($rawBody),
        'env_secret_set' => wasend_env('WEBHOOK_SECRET', '') !== '',
        'hint' => $secret === ''
            ? 'webhook_secret decrypts to empty. Set WEBHOOK_SECRET in /config/.env to bypass DB encryption.'
            : 'Secret loaded but signatures dont match. HF Spaces WEBHOOK_SECRET must match PHP webhook_secret exactly.',
    ]);
    exit;
}

$payload = json_decode($rawBody, true);
$type    = $payload['type'] ?? '';
if ($type !== 'cron_tick') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_type', 'type' => $type]);
    exit;
}

$pdo          = wasend_db();
$leadRepo     = new LeadRepo();
$msgRepo      = new MessageRepo();
$campaignRepo = new CampaignRepo();
$node         = new NodeClient();

$result = [
    'validated'        => 0,
    'validated_valid'  => 0,
    'validated_invalid'=> 0,
    'sent_lead_id'     => null,
    'sent_skip_reason' => null,
    'cleanup_purged'   => 0,
    'engine_state'     => null,
    'errors'           => [],
];

if (!$node->isConfigured()) {
    $result['errors'][] = 'node_not_configured';
    echo json_encode(['ok' => true, 'data' => $result]);
    exit;
}

// Quick engine state check
try {
    $status = $node->getStatus();
    // Node /status returns flat: { ok:true, state:'ready', ready:bool, ... }
    $state  = (string) ($status['state'] ?? 'unknown');
    $result['engine_state'] = $state;
    $engineReady = in_array(strtolower($state), ['ready','connected','authenticated'], true);
} catch (Throwable $e) {
    $engineReady = false;
    $result['engine_state'] = 'error';
    $result['errors'][] = 'status_failed: ' . $e->getMessage();
}

// ---------------------------------------------------------------------
// 1) Validate up to 5 pending leads (small batch for fast tick)
// ---------------------------------------------------------------------
if ($engineReady) {
    $batch = 5;
    $stmt  = $pdo->prepare("SELECT id, phone_number FROM leads WHERE whatsapp_status = 'pending' ORDER BY id ASC LIMIT :lim");
    $stmt->bindValue(':lim', $batch, PDO::PARAM_INT);
    $stmt->execute();
    $pending = $stmt->fetchAll();

    foreach ($pending as $lead) {
        try {
            $res = $node->checkNumber($lead['phone_number']);
            if (!empty($res['ok'])) {
                // Node /check-number returns flat: { ok:true, on_whatsapp:bool, jid:'...', phone:'...' }
                $onWa = (bool) ($res['on_whatsapp'] ?? false);
                $jid  = $res['jid'] ?? null;
                if ($onWa) {
                    $leadRepo->setWhatsappStatus((int)$lead['id'], 'valid', $jid);
                    $result['validated_valid']++;
                } else {
                    $leadRepo->setWhatsappStatus((int)$lead['id'], 'not_on_whatsapp', null);
                    $result['validated_invalid']++;
                }
                $result['validated']++;
            } else {
                $leadRepo->setWhatsappStatus((int)$lead['id'], 'failed', null);
                $result['errors'][] = 'validate_failed_lead_' . $lead['id'];
            }
            usleep(random_int(800_000, 1_500_000));
        } catch (Throwable $e) {
            $result['errors'][] = 'validate_exception: ' . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------------
// 2) Run one campaign tick (mirrors scripts/campaign.php exactly)
// ---------------------------------------------------------------------
$camp = $campaignRepo->getDefault();

if (!$camp) {
    $result['sent_skip_reason'] = 'no_campaign';
} elseif ($camp['status'] !== 'running') {
    $result['sent_skip_reason'] = 'status_' . $camp['status'];
} elseif (!filter_var(wasend_setting('campaign_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
    $result['sent_skip_reason'] = 'campaign_disabled';
} elseif (!$engineReady) {
    $result['sent_skip_reason'] = 'engine_not_ready';
} else {
    $campaignId = (int) $camp['id'];

    if (!empty($camp['last_sent_at']) && date('Y-m-d', strtotime($camp['last_sent_at'])) !== date('Y-m-d')) {
        $campaignRepo->resetDailyCounter($campaignId);
        $camp['sent_today'] = 0;
    }

    $startHour = (int) wasend_setting('working_hours_start', 10);
    $endHour   = (int) wasend_setting('working_hours_end', 20);
    $nowHour   = (int) date('H');

    $dailyLimit = (int) ($camp['daily_limit'] ?: wasend_setting('daily_send_limit', 80));
    $sentToday  = (int) $camp['sent_today'];

    if ($nowHour < $startHour || $nowHour >= $endHour) {
        $result['sent_skip_reason'] = "outside_hours_$nowHour";
    } elseif ($sentToday >= $dailyLimit) {
        $result['sent_skip_reason'] = 'daily_limit_reached';
    } elseif (!empty($camp['next_run_at']) && new DateTimeImmutable($camp['next_run_at']) > new DateTimeImmutable('now')) {
        $result['sent_skip_reason'] = 'pacing_active';
    } else {
        try {
            $leads = $leadRepo->nextForCampaign($campaignId, 1);
            if (!$leads) {
                $result['sent_skip_reason'] = 'no_eligible_leads';
            } else {
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
                    $result['sent_skip_reason'] = 'empty_message';
                } else {
                    $jid = $lead['whatsapp_jid'] ?: ($lead['phone_number'] . '@c.us');
                    $res = $node->sendMessage($jid, $text, [
                        'lead_id'  => $leadId,
                        'campaign' => $campaignId,
                        'source'   => 'cron_tick',
                    ]);

                    if (!empty($res['ok'])) {
                        $waId  = $res['wa_message_id'] ?? null;
                        $msgId = $msgRepo->insertOutbound($leadId, $text, $waId, true, 'system', [
                            'campaign_id' => $campaignId,
                            'ai_source'   => $gen['source'],
                            'pitch_type'  => $ctx['pitch_type'],
                            'language'    => $ctx['language'],
                            'services'    => $ctx['services'],
                            'trigger'     => 'cron',
                        ]);
                        $leadRepo->setOutreachStatus($leadId, 'sent', date('Y-m-d H:i:s'));
                        $campaignRepo->markQueueSent($queueId, $msgId);
                        $campaignRepo->incrementSent($campaignId);

                        $delay = (int) random_int(
                            (int) max(60, $camp['min_delay_seconds']),
                            (int) max($camp['min_delay_seconds'], $camp['max_delay_seconds'])
                        );
                        $next = (new DateTimeImmutable("+{$delay} seconds"))->format('Y-m-d H:i:s');
                        $campaignRepo->update($campaignId, ['next_run_at' => $next]);

                        $result['sent_lead_id']      = $leadId;
                        $result['sent_business']     = $lead['business_name'];
                        $result['sent_next_in_s']    = $delay;
                        $result['sent_ai_source']    = $gen['source'];
                        wasend_log('info', 'cron_campaign', 'sent', [
                            'lead_id' => $leadId, 'wa_id' => $waId, 'next_in_s' => $delay,
                        ]);
                    } else {
                        $err = (string) ($res['error'] ?? 'send_failed');
                        $campaignRepo->markQueueFailed($queueId, $err);
                        $campaignRepo->incrementFailed($campaignId);
                        $leadRepo->setOutreachStatus($leadId, 'failed');
                        $result['sent_skip_reason'] = 'send_failed:' . $err;
                        wasend_log('error', 'cron_campaign', 'send_failed', ['lead_id' => $leadId, 'err' => $err]);
                    }
                }
            }
        } catch (Throwable $e) {
            $result['errors'][] = 'campaign_exception: ' . $e->getMessage();
            wasend_log('error', 'cron_campaign', 'exception', ['err' => $e->getMessage()]);
        }
    }
}

// ---------------------------------------------------------------------
// 3) Cleanup (expired socket tokens, very old webhook log entries)
// ---------------------------------------------------------------------
try {
    $r1 = $pdo->exec('DELETE FROM socket_tokens WHERE expires_at < NOW()');
    $r2 = $pdo->exec('DELETE FROM webhook_log WHERE received_at < DATE_SUB(NOW(), INTERVAL 14 DAY)');
    $result['cleanup_purged'] = (int) ($r1 ?? 0) + (int) ($r2 ?? 0);
} catch (Throwable $e) {
    $result['errors'][] = 'cleanup_exception: ' . $e->getMessage();
}

echo json_encode(['ok' => true, 'data' => $result]);
