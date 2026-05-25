<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$repo = new SettingsRepo();
$rows = $repo->all(true);

// group by category
$grouped = [];
foreach ($rows as $r) {
    $cat = $r['category'] ?: 'general';
    $grouped[$cat][] = $r;
}

json_ok([
    'categories' => $grouped,
    'flat'       => $rows,
]);
