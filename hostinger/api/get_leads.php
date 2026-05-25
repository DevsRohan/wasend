<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$repo = new LeadRepo();
$page = paginate_args($_GET, (int) wasend_setting('items_per_page', 50));

$filters = [
    'search'          => clean_str($_GET['q'] ?? '', 200),
    'whatsapp_status' => clean_str($_GET['ws'] ?? '', 30),
    'outreach_status' => clean_str($_GET['os'] ?? '', 30),
    'pitch_type'      => clean_str($_GET['pt'] ?? '', 8),
    'city'            => clean_str($_GET['city'] ?? '', 120),
    'state'           => clean_str($_GET['state'] ?? '', 120),
    'has_unread'      => clean_bool($_GET['unread'] ?? 0),
    'pinned'          => clean_bool($_GET['pinned'] ?? 0),
    'tab'             => clean_str($_GET['tab'] ?? '', 20),
];

$res = $repo->listFiltered($filters, $page['offset'], $page['per_page']);

json_ok([
    'rows'     => $res['rows'],
    'total'    => $res['total'],
    'page'     => $page['page'],
    'per_page' => $page['per_page'],
    'has_more' => ($page['offset'] + count($res['rows'])) < $res['total'],
]);
