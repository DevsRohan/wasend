<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$leadId = clean_int($_GET['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$leadRepo = new LeadRepo();
$lead     = $leadRepo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

$msgRepo = new MessageRepo();
$messages = $msgRepo->listByLead($leadId, 500);

json_ok([
    'lead'     => $lead,
    'messages' => $messages,
]);
