<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(true);

$body   = read_json_input();
$leadId = clean_int($body['lead_id'] ?? 0);
$note   = clean_str($body['note'] ?? '', 4000);
if ($leadId <= 0 || $note === '') json_error('invalid_input', 422);

$repo = new LeadRepo();
$lead = $repo->find($leadId);
if (!$lead) json_error('lead_not_found', 404);

$ts       = date('Y-m-d H:i');
$user     = $_SESSION['username'] ?? 'admin';
$entry    = "[$ts $user] $note";
$existing = (string) ($lead['notes'] ?? '');
$merged   = $existing === '' ? $entry : $existing . "\n" . $entry;

$repo->update($leadId, ['notes' => $merged]);
wasend_log('info', 'lead', 'note_added', ['id' => $leadId]);

json_ok(['notes' => $merged]);
