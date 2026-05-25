<?php
require_once __DIR__ . '/_bootstrap.php';
api_guard(false);

$limit    = max(10, min(500, (int) ($_GET['limit'] ?? 100)));
$level    = clean_str($_GET['level'] ?? '', 20) ?: null;
$category = clean_str($_GET['category'] ?? '', 50) ?: null;

$repo = new LogRepo();
$rows = $repo->listRecent($limit, $level, $category);
$cats = $repo->categories();

json_ok([
    'rows'       => $rows,
    'categories' => $cats,
    'count'      => count($rows),
]);
