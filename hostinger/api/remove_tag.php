<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
$tag    = clean_str($body['tag'] ?? '', 60);
if ($leadId <= 0 || $tag === '') json_error('invalid_input', 422);

$repo = new LeadRepo();
$lead = $repo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

$tags = array_values(array_diff(tags_to_array($lead['tags'] ?? ''), [$tag]));
$repo->update($leadId, ['tags' => tags_to_string($tags)]);

json_ok(['tags' => $tags]);
