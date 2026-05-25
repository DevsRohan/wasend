<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$repo = new LeadRepo();
$ok   = $repo->delete($leadId);
if (!$ok) json_error('delete_failed', 500);

wasend_log('warning', 'lead', 'deleted', ['id' => $leadId]);
json_ok(['deleted' => true]);
