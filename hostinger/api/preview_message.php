<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

if (!filter_var(wasend_setting('feature_ai_preview', '1'), FILTER_VALIDATE_BOOLEAN)) {
    json_error('feature_disabled', 403);
}

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$leadRepo = new LeadRepo();
$lead     = $leadRepo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

$ownerName     = (string) wasend_setting('owner_name', 'Our team');
$ownerServices = (string) wasend_setting('owner_services', '');
$ctx = Personalization::buildContext($lead, ['name' => $ownerName, 'services' => $ownerServices]);

$groq   = new Groq();
$result = $groq->generateOutreach($ctx);

json_ok([
    'message' => $result['message'],
    'source'  => $result['source'],
    'context' => [
        'pitch_type' => $ctx['pitch_type'],
        'language'   => $ctx['language'],
        'services'   => $ctx['services'],
    ],
    'error'   => $result['error'],
]);
