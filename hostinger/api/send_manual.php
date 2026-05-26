<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
$text   = clean_str($body['text'] ?? '', 4000);

if ($leadId <= 0)  json_error('lead_id_required', 422);
if ($text === '')  json_error('text_required', 422);

$leadRepo = new LeadRepo();
$lead     = $leadRepo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

if (in_array($lead['whatsapp_status'], ['invalid', 'not_on_whatsapp'], true)) {
    json_error('lead_not_on_whatsapp', 400);
}

$jid = $lead['whatsapp_jid'] ?: ($lead['phone_number'] . '@c.us');

$node = new NodeClient();
if (!$node->isConfigured()) json_error('node_not_configured', 503);

$res = $node->sendMessage($jid, $text, ['source' => 'manual']);

$msgRepo = new MessageRepo();
if (!empty($res['ok'])) {
    // Node /send-message returns flat: { ok:true, wa_message_id:'...', jid:'...', status:'sent' }
    $waId = $res['wa_message_id'] ?? null;
    $messageId = $msgRepo->insertOutbound($leadId, $text, $waId, false, 'user', ['source' => 'manual']);
    $leadRepo->setOutreachStatus($leadId, in_array($lead['outreach_status'], ['replied','sent','delivered','read'], true) ? $lead['outreach_status'] : 'sent', date('Y-m-d H:i:s'));
    wasend_log('info', 'manual_send', 'sent', ['lead_id' => $leadId, 'wa_id' => $waId]);

    json_ok([
        'message_id' => $messageId,
        'wa_message_id' => $waId,
    ]);
}

wasend_log('error', 'manual_send', 'failed', ['lead_id' => $leadId, 'res' => $res]);
json_error($res['error'] ?? 'send_failed', 502, ['detail' => $res]);
