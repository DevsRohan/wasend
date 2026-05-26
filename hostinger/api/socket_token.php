<?php
require_once __DIR__ . '/_bootstrap.php';
$user = api_guard(false);

$pdo = wasend_db();

// Cleanup expired
$pdo->exec('DELETE FROM socket_tokens WHERE expires_at < NOW()');

$token = bin2hex(random_bytes(24));
$exp   = date('Y-m-d H:i:s', time() + 5 * 60); // 5 min

$stmt = $pdo->prepare('INSERT INTO socket_tokens (token, user_id, expires_at) VALUES (?, ?, ?)');
$stmt->execute([$token, (int) $user['id'], $exp]);

json_ok([
    'token'      => $token,
    'expires_at' => $exp,
    'socket_url' => (string) (wasend_setting('socket_url', '') ?: wasend_setting('node_api_url', '')),
]);
