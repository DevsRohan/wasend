<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$msgRepo  = new MessageRepo();
$leadRepo = new LeadRepo();

$msgRepo->markReadForLead($leadId);
$leadRepo->clearUnread($leadId);

json_ok(['marked' => true]);
