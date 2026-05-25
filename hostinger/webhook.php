<?php
/**
 * webhook.php - Receives events from Hugging Face Node engine.
 *
 * Auth: HMAC-SHA256 over raw body using webhook_secret.
 *   Header: X-Webhook-Signature: sha256=<hex>
 *   Header: X-Webhook-Event-Id: <unique id> (used for dedup)
 *
 * Event types (from Node):
 *   - message_inbound
 *   - message_outbound_ack    (delivered/read for an outbound msg)
 *   - engine_state            (connected, disconnected, qr, ready)
 *   - lead_validated          (whatsapp_status check result, optional)
 */
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

spl_autoload_register(function ($class) {
    $map = [
        'LeadRepo'     => '/lead_repo.php',
        'MessageRepo'  => '/message_repo.php',
        'CampaignRepo' => '/campaign_repo.php',
    ];
    if (isset($map[$class])) require_once WASEND_INCLUDES . $map[$class];
});

header('Content-Type: application/json');

$rawBody  = file_get_contents('php://input') ?: '';
$sig      = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$eventId  = $_SERVER['HTTP_X_WEBHOOK_EVENT_ID']  ?? null;
$secret   = (string) wasend_setting('webhook_secret', '');

// Optional IP whitelist
$ipAllow = trim((string) wasend_setting('webhook_ip_whitelist', ''));
if ($ipAllow !== '') {
    $ips = array_filter(array_map('trim', explode(',', $ipAllow)));
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remote, $ips, true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'ip_not_allowed']);
        wasend_log('warning', 'webhook', 'ip_blocked', ['ip' => $remote]);
        exit;
    }
}

if (!auth_verify_webhook($rawBody, $sig, $secret)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'bad_signature']);
    wasend_log('warning', 'webhook', 'bad_signature', ['sig_present' => (bool) $sig]);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload) || empty($payload['type'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_payload']);
    exit;
}

$type = (string) $payload['type'];
$pdo  = wasend_db();

// Dedup using event_id
$logSql = 'INSERT INTO webhook_log (event_id, event_type, payload, signature_ok, processed) VALUES (?, ?, ?, 1, 0)';
try {
    if ($eventId) {
        // Insert ignoring duplicates
        $stmt = $pdo->prepare('INSERT IGNORE INTO webhook_log (event_id, event_type, payload, signature_ok, processed) VALUES (?, ?, ?, 1, 0)');
        $stmt->execute([$eventId, $type, $rawBody]);
        if ($stmt->rowCount() === 0) {
            // duplicate
            echo json_encode(['ok' => true, 'duplicate' => true]);
            exit;
        }
        $logId = (int) $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare($logSql);
        $stmt->execute([null, $type, $rawBody]);
        $logId = (int) $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    wasend_log('error', 'webhook', 'log_insert_failed', ['err' => $e->getMessage()]);
    $logId = 0;
}

$leadRepo = new LeadRepo();
$msgRepo  = new MessageRepo();
$camRepo  = new CampaignRepo();

$markProcessed = function (int $id, ?string $err = null) use ($pdo) {
    if ($id <= 0) return;
    $stmt = $pdo->prepare('UPDATE webhook_log SET processed = 1, processed_at = NOW(), error_message = ? WHERE id = ?');
    $stmt->execute([$err, $id]);
};

try {
    switch ($type) {

        // ----------------------------------------------------------------
        case 'message_inbound': {
            $jid    = (string) ($payload['from'] ?? $payload['jid'] ?? '');
            $text   = (string) ($payload['text'] ?? '');
            $waId   = $payload['wa_message_id'] ?? null;
            $msgType= (string) ($payload['message_type'] ?? 'text');
            if ($jid === '') throw new RuntimeException('missing_from');

            $lead = $leadRepo->findByJid($jid);
            if (!$lead) {
                // Inbound from unknown number -> create a minimal lead record
                $phone = preg_replace('/@.*/', '', $jid) ?? '';
                $id = $leadRepo->upsert([
                    'business_name'  => 'Unknown ' . substr($phone, -4),
                    'phone_number'   => $phone,
                    'phone_raw'      => $phone,
                    'website_status' => 'unknown',
                    'pitch_type'     => 'unknown',
                    'source'         => 'inbound_unknown',
                ]);
                $leadRepo->setWhatsappStatus($id, 'valid', $jid);
                $lead = $leadRepo->find($id);
            }

            $msgRepo->insertInbound((int) $lead['id'], $text, $waId, $msgType, ['raw' => $payload]);
            $leadRepo->markReplied((int) $lead['id']);

            // Block all pending queue items for this lead (stop automation)
            $camRepo->blockQueueForLead((int) $lead['id']);

            $defaultCamp = $camRepo->getDefault();
            if ($defaultCamp) $camRepo->incrementReplied((int) $defaultCamp['id']);

            wasend_log('info', 'webhook', 'inbound_processed', [
                'lead_id' => (int) $lead['id'],
                'wa_id'   => $waId,
                'text_preview' => mb_substr($text, 0, 100),
            ]);
            $markProcessed($logId);
            echo json_encode(['ok' => true, 'lead_id' => (int) $lead['id']]);
            break;
        }

        // ----------------------------------------------------------------
        case 'message_outbound_ack': {
            $waId   = (string) ($payload['wa_message_id'] ?? '');
            $status = (string) ($payload['status'] ?? 'delivered');
            if ($waId === '') throw new RuntimeException('missing_wa_id');
            if (!in_array($status, ['queued','sent','delivered','read','failed'], true)) {
                $status = 'delivered';
            }
            $msgRepo->updateStatusByWaId($waId, $status);

            // mirror onto lead's outreach_status if it's an outreach message
            $existing = $msgRepo->findByWaId($waId);
            if ($existing) {
                if ($status === 'failed') {
                    $leadRepo->setOutreachStatus((int) $existing['lead_id'], 'failed');
                } elseif (in_array($status, ['delivered','read'], true)) {
                    // only upgrade, don't overwrite replied/etc.
                    $lead = $leadRepo->find((int) $existing['lead_id']);
                    if ($lead && in_array($lead['outreach_status'], ['sent','delivered'], true)) {
                        $leadRepo->setOutreachStatus((int) $existing['lead_id'], $status);
                    }
                }
            }
            $markProcessed($logId);
            echo json_encode(['ok' => true]);
            break;
        }

        // ----------------------------------------------------------------
        case 'engine_state': {
            $state = (string) ($payload['state'] ?? 'unknown');
            wasend_log('info', 'engine', 'state_change', ['state' => $state]);
            $markProcessed($logId);
            echo json_encode(['ok' => true, 'state' => $state]);
            break;
        }

        // ----------------------------------------------------------------
        case 'lead_validated': {
            $phone = (string) ($payload['phone'] ?? '');
            $onWa  = (bool)   ($payload['on_whatsapp'] ?? false);
            $jid   = $payload['jid'] ?? null;
            if ($phone === '') throw new RuntimeException('missing_phone');
            $lead = $leadRepo->findByPhone($phone);
            if ($lead) {
                $leadRepo->setWhatsappStatus((int) $lead['id'],
                    $onWa ? 'valid' : 'not_on_whatsapp',
                    $onWa ? $jid : null
                );
            }
            $markProcessed($logId);
            echo json_encode(['ok' => true]);
            break;
        }

        // ----------------------------------------------------------------
        default:
            wasend_log('warning', 'webhook', 'unknown_type', ['type' => $type]);
            $markProcessed($logId, 'unknown_type');
            http_response_code(202);
            echo json_encode(['ok' => true, 'ignored' => true, 'type' => $type]);
            break;
    }
} catch (Throwable $e) {
    $markProcessed($logId, $e->getMessage());
    wasend_log('error', 'webhook', 'process_failed', ['err' => $e->getMessage(), 'type' => $type]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
