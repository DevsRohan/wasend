<?php
/**
 * app.php - Application bootstrap, constants, configuration
 *
 * Loaded by every entry point. Reads:
 *   1. /config/.env (file-based overrides)
 *   2. settings table (DB-backed, hot reloadable from UI)
 *
 * Hostinger shared-hosting safe.
 */
declare(strict_types=1);

if (defined('WASEND_BOOTED')) {
    return;
}
define('WASEND_BOOTED', true);

// ---------------------------------------------------------------------
// Path constants
// ---------------------------------------------------------------------
define('WASEND_ROOT',     dirname(__DIR__));                   // /hostinger
define('WASEND_CONFIG',   WASEND_ROOT . '/config');
define('WASEND_INCLUDES', WASEND_ROOT . '/includes');
define('WASEND_API',      WASEND_ROOT . '/api');
define('WASEND_ASSETS',   WASEND_ROOT . '/assets');
define('WASEND_UPLOADS',  WASEND_ROOT . '/uploads');
define('WASEND_LOGS',     WASEND_ROOT . '/logs');
define('WASEND_SCRIPTS',  WASEND_ROOT . '/scripts');

// ---------------------------------------------------------------------
// Error handling - production safe (logs only)
// ---------------------------------------------------------------------
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', WASEND_LOGS . '/php_errors.log');
error_reporting(E_ALL);

// Default timezone (Asia/Kolkata makes Indian outreach pacing predictable)
date_default_timezone_set('Asia/Kolkata');

// ---------------------------------------------------------------------
// Load .env helper
// ---------------------------------------------------------------------
require_once WASEND_CONFIG . '/env.php';

// ---------------------------------------------------------------------
// Database credentials (override via /config/.env)
// ---------------------------------------------------------------------
define('DB_HOST',     wasend_env('DB_HOST',     'localhost'));
define('DB_PORT',     (int) wasend_env('DB_PORT', '3306'));
define('DB_NAME',     wasend_env('DB_NAME',     'wasend_crm'));
define('DB_USER',     wasend_env('DB_USER',     'root'));
define('DB_PASS',     wasend_env('DB_PASS',     ''));
define('DB_CHARSET',  wasend_env('DB_CHARSET',  'utf8mb4'));

// ---------------------------------------------------------------------
// App identity
// ---------------------------------------------------------------------
define('APP_NAME',    wasend_env('APP_NAME',    'Wasend'));
define('APP_ENV',     wasend_env('APP_ENV',     'production'));
define('APP_DEBUG',   filter_var(wasend_env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN));
define('APP_URL',     rtrim((string) wasend_env('APP_URL', ''), '/'));

// Master encryption key (used to encrypt DB-stored secrets at rest)
define('APP_KEY',     wasend_env('APP_KEY',     'change-me-32-bytes-min-secret-key!!'));

// ---------------------------------------------------------------------
// Session config
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    session_set_cookie_params($cookieParams);
    @session_name('WASEND_SID');
    @session_start();
}

// ---------------------------------------------------------------------
// Helper: load setting from DB (with file env fallback)
//
// PRECEDENCE for SECRET-type settings (webhook_secret, node_api_key, groq_api_key):
//   1. UPPERCASE env var in /config/.env  (highest priority - bypasses any DB
//      encryption issues that arise if APP_KEY changed after the secret was
//      saved through the UI)
//   2. DB-stored value (decrypted with current APP_KEY)
//   3. provided $default
//
// PRECEDENCE for non-secret settings:
//   1. DB value (canonical source - editable from UI)
//   2. UPPERCASE env var
//   3. $default
// ---------------------------------------------------------------------
if (!function_exists('wasend_setting')) {
    /** @var string[] Setting keys that ALWAYS prefer env over DB */
    function wasend_secret_keys(): array {
        return ['webhook_secret', 'node_api_key', 'groq_api_key'];
    }

    function wasend_setting(string $key, $default = null)
    {
        // Secrets: ENV beats DB, so a typo / re-keyed APP_KEY can't kill the
        // entire integration.
        if (in_array($key, wasend_secret_keys(), true)) {
            $envVal = wasend_env(strtoupper($key), null);
            if ($envVal !== null && $envVal !== '') {
                return (string) $envVal;
            }
        }

        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                /** @var PDO $pdo */
                require_once WASEND_CONFIG . '/db.php';
                $pdo = wasend_db();
                $rows = $pdo->query('SELECT setting_key, setting_value, value_type, is_secret FROM settings')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $val = $r['setting_value'];
                    if ($r['is_secret'] && $val !== null && $val !== '') {
                        $val = wasend_decrypt((string) $val);
                    }
                    $cache[$r['setting_key']] = wasend_cast_setting($val, $r['value_type']);
                }
            } catch (Throwable $e) {
                // DB not ready (e.g. install) - fall back to env only
            }
        }
        if (array_key_exists($key, $cache)) {
            $v = $cache[$key];
            // If a secret decrypted to empty (stale APP_KEY), fall through to env.
            if (in_array($key, wasend_secret_keys(), true) && ($v === '' || $v === null)) {
                $envVal = wasend_env(strtoupper($key), null);
                if ($envVal !== null && $envVal !== '') return (string) $envVal;
            }
            return $v;
        }
        return wasend_env(strtoupper($key), $default);
    }

    function wasend_cast_setting($val, string $type)
    {
        if ($val === null) {
            return null;
        }
        switch ($type) {
            case 'int':    return (int) $val;
            case 'float':  return (float) $val;
            case 'bool':   return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            case 'json':   return json_decode((string) $val, true);
            default:       return (string) $val;
        }
    }
}

// ---------------------------------------------------------------------
// Symmetric secret encryption (for storing API keys in DB)
// AES-256-GCM with key derived from APP_KEY
// ---------------------------------------------------------------------
if (!function_exists('wasend_encrypt')) {
    function wasend_kdf(): string
    {
        return hash('sha256', (string) APP_KEY, true);
    }
    function wasend_encrypt(string $plain): string
    {
        if ($plain === '') return '';
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, 'aes-256-gcm', wasend_kdf(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) return '';
        return 'enc:v1:' . base64_encode($iv . $tag . $ct);
    }
    function wasend_decrypt(string $cipher): string
    {
        if ($cipher === '' || strpos($cipher, 'enc:v1:') !== 0) {
            return $cipher; // legacy/plain
        }
        $raw = base64_decode(substr($cipher, 7), true);
        if ($raw === false || strlen($raw) < 28) return '';
        $iv  = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct  = substr($raw, 28);
        $pt  = openssl_decrypt($ct, 'aes-256-gcm', wasend_kdf(), OPENSSL_RAW_DATA, $iv, $tag);
        return $pt === false ? '' : $pt;
    }
}

// ---------------------------------------------------------------------
// Standard response helpers
// ---------------------------------------------------------------------
require_once WASEND_INCLUDES . '/helpers.php';
