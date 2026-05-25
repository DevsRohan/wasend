<?php
/**
 * auth.php - Dashboard session auth + webhook HMAC verification
 */
declare(strict_types=1);

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;
        try {
            $pdo  = wasend_db();
            $stmt = $pdo->prepare('SELECT id, username, email, role, is_active FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $_SESSION['user_id']]);
            $u = $stmt->fetch();
            if (!$u || !$u['is_active']) return null;
            return $u;
        } catch (Throwable $e) {
            return null;
        }
    }

    function auth_check(bool $apiMode = false): array
    {
        $u = auth_user();
        if (!$u) {
            if ($apiMode) {
                json_error('unauthenticated', 401);
            }
            header('Location: ' . (defined('APP_URL') && APP_URL ? APP_URL : '') . '/login.php');
            exit;
        }
        return $u;
    }

    function auth_login(string $username, string $password): ?array
    {
        $pdo  = wasend_db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u) return null;
        if (!password_verify($password, $u['password_hash'])) return null;

        // Refresh session
        @session_regenerate_id(true);
        $_SESSION['user_id']    = (int) $u['id'];
        $_SESSION['username']   = $u['username'];
        $_SESSION['login_time'] = time();

        $pdo->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, (int) $u['id']]);

        wasend_log('info', 'auth', 'login_success', ['user' => $u['username']]);
        return $u;
    }

    function auth_logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        @session_destroy();
    }

    /**
     * Verify webhook HMAC signature.
     * Header: X-Webhook-Signature: sha256=<hex>
     */
    function auth_verify_webhook(string $rawBody, ?string $headerSig, string $secret): bool
    {
        if ($secret === '' || !$headerSig) return false;
        $sig = $headerSig;
        if (str_starts_with($sig, 'sha256=')) {
            $sig = substr($sig, 7);
        }
        return wasend_verify_hmac($rawBody, $sig, $secret);
    }
}
