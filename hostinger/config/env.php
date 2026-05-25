<?php
/**
 * env.php - Lightweight .env style loader for Hostinger
 * Loads from /config/.env (next to this file) if present.
 * Falls back silently if missing.
 */
declare(strict_types=1);

if (!function_exists('wasend_env')) {
    /**
     * @var array<string,string>
     */
    $GLOBALS['__WASEND_ENV'] = $GLOBALS['__WASEND_ENV'] ?? [];

    function wasend_env_load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // strip wrapping quotes
            if (strlen($v) >= 2 && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                $v = substr($v, 1, -1);
            }
            $GLOBALS['__WASEND_ENV'][$k] = $v;
            if (getenv($k) === false) {
                @putenv("$k=$v");
            }
            $_ENV[$k] = $v;
        }
    }

    function wasend_env(string $key, $default = null)
    {
        if (array_key_exists($key, $GLOBALS['__WASEND_ENV'])) {
            return $GLOBALS['__WASEND_ENV'][$key];
        }
        $v = getenv($key);
        if ($v !== false) {
            return $v;
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        return $default;
    }
}

// Auto-load .env if present
wasend_env_load(__DIR__ . '/.env');
