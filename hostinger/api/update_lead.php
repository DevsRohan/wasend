<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
if ($leadId <= 0) json_error('lead_id_required', 422);

$fields = [];
$allowed = ['business_name','address','locality','city','state','website_url',
            'website_status','rating','review_count','pitch_type','language_preference',
            'tags','notes','is_pinned'];
foreach ($allowed as $k) {
    if (array_key_exists($k, $body)) {
        $fields[$k] = is_string($body[$k]) ? clean_str($body[$k], 1500) : $body[$k];
    }
}
if (!$fields) json_error('no_fields', 422);

$repo = new LeadRepo();
$ok   = $repo->update($leadId, $fields);
if (!$ok) json_error('update_failed', 500);

wasend_log('info', 'lead', 'updated', ['id' => $leadId, 'fields' => array_keys($fields)]);
json_ok(['updated' => true, 'lead' => $repo->find($leadId)]);
