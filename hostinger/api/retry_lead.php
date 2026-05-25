<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$repo = new LeadRepo();
$lead = $repo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

if ($lead['outreach_status'] === 'replied') {
    json_error('lead_replied_no_retry', 400);
}

$repo->setOutreachStatus($leadId, 'pending');
if ($lead['whatsapp_status'] === 'failed') {
    $repo->setWhatsappStatus($leadId, 'pending');
}
wasend_log('info', 'lead', 'retry_queued', ['id' => $leadId]);
json_ok(['queued' => true]);
