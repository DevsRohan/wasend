<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$leadId = clean_int($_GET['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$repo  = new LeadRepo();
$lead  = $repo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

$msgRepo  = new MessageRepo();
$messages = $msgRepo->listByLead($leadId, 25);

$ownerName     = (string) wasend_setting('owner_name', 'Our team');
$ownerServices = (string) wasend_setting('owner_services', '');
$ctx = Personalization::buildContext($lead, ['name' => $ownerName, 'services' => $ownerServices]);

$tags = tags_to_array($lead['tags'] ?? '');

json_ok([
    'lead'       => $lead,
    'tags'       => $tags,
    'context'    => [
        'pitch_type' => $ctx['pitch_type'],
        'language'   => $ctx['language'],
        'services'   => $ctx['services'],
    ],
    'messages'   => $messages,
    'message_count' => count($messages),
]);
