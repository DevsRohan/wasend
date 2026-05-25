<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);

$leadRepo = new LeadRepo();
$lead     = $leadRepo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

$node = new NodeClient();
if (!$node->isConfigured()) json_error('node_not_configured', 503);

$res = $node->checkNumber($lead['phone_number']);
$onWa = false;
$jid  = null;
if (!empty($res['ok'])) {
    $onWa = (bool) ($res['data']['on_whatsapp'] ?? ($res['on_whatsapp'] ?? false));
    $jid  = $res['data']['jid'] ?? ($res['jid'] ?? null);
}

if ($onWa) {
    $leadRepo->setWhatsappStatus($leadId, 'valid', $jid);
} else {
    $leadRepo->setWhatsappStatus($leadId, 'not_on_whatsapp', null);
}

json_ok([
    'on_whatsapp' => $onWa,
    'jid'         => $jid,
]);
