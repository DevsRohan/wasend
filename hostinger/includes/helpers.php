<?php
/**
 * helpers.php - Common utilities used across the app
 */
declare(strict_types=1);

// ---------------------------------------------------------------------
// JSON response
// ---------------------------------------------------------------------
if (!function_exists('json_response')) {
    function json_response($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    function json_ok($data = null, array $extra = []): void
    {
        $resp = ['ok' => true];
        if ($data !== null) $resp['data'] = $data;
        json_response(array_merge($resp, $extra), 200);
    }
    function json_error(string $message, int $status = 400, array $extra = []): void
    {
        json_response(array_merge(['ok' => false, 'error' => $message], $extra), $status);
    }
}

// ---------------------------------------------------------------------
// Input sanitization
// ---------------------------------------------------------------------
if (!function_exists('clean_str')) {
    function clean_str($v, int $max = 500): string
    {
        if ($v === null) return '';
        $s = trim((string) $v);
        $s = preg_replace('/\x00/', '', $s) ?? '';
        if ($max > 0 && mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max);
        }
        return $s;
    }
    function clean_int($v, int $default = 0): int
    {
        if ($v === null || $v === '') return $default;
        return (int) $v;
    }
    function clean_float($v, float $default = 0.0): float
    {
        if ($v === null || $v === '') return $default;
        return (float) $v;
    }
    function clean_bool($v): int
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }
    function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// ---------------------------------------------------------------------
// Phone number normalization (India default, supports +)
// Returns: ['e164' => '919876543210', 'jid' => '919876543210@c.us', 'raw' => '...']
// ---------------------------------------------------------------------
if (!function_exists('normalize_phone')) {
    function normalize_phone(string $raw, string $defaultCountry = '91'): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['e164' => '', 'jid' => '', 'raw' => $raw, 'valid' => false];
        }
        // strip everything except digits and leading +
        $hasPlus = (substr($raw, 0, 1) === '+');
        $digits  = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return ['e164' => '', 'jid' => '', 'raw' => $raw, 'valid' => false];
        }
        // strip leading 0
        if (!$hasPlus && substr($digits, 0, 1) === '0') {
            $digits = ltrim($digits, '0');
        }
        // assume Indian if 10 digits and no +
        if (!$hasPlus && strlen($digits) === 10) {
            $digits = $defaultCountry . $digits;
        }
        // common India case: +91 then 10 digits
        // basic length sanity (E.164: 8-15 digits)
        $valid = (strlen($digits) >= 8 && strlen($digits) <= 15);
        return [
            'e164'  => $digits,
            'jid'   => $valid ? ($digits . '@c.us') : '',
            'raw'   => $raw,
            'valid' => $valid,
        ];
    }
}

// ---------------------------------------------------------------------
// Logging (writes to logs/app.log + activity_log table best-effort)
// ---------------------------------------------------------------------
if (!function_exists('wasend_log')) {
    function wasend_log(string $level, string $category, string $message, array $meta = []): void
    {
        $line = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $category,
            $message,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents(WASEND_LOGS . '/app.log', $line, FILE_APPEND | LOCK_EX);

        // Best-effort DB activity log
        try {
            $pdo = wasend_db();
            $stmt = $pdo->prepare(
                'INSERT INTO activity_log (level, category, action, message, meta_json, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $level,
                $category,
                $category . '.' . substr(md5($message), 0, 8),
                mb_substr($message, 0, 1000),
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // ignore - logs file already has it
        }
    }
}

// ---------------------------------------------------------------------
// Tag helpers
// ---------------------------------------------------------------------
if (!function_exists('tags_to_array')) {
    function tags_to_array(?string $tags): array
    {
        if (!$tags) return [];
        $arr = array_filter(array_map('trim', explode(',', $tags)));
        return array_values(array_unique($arr));
    }
    function tags_to_string(array $tags): string
    {
        $tags = array_filter(array_map(fn($t) => trim((string)$t), $tags));
        return implode(',', array_unique($tags));
    }
}

// ---------------------------------------------------------------------
// Time helpers
// ---------------------------------------------------------------------
if (!function_exists('time_ago')) {
    function time_ago(?string $datetime): string
    {
        if (!$datetime) return '-';
        $ts = strtotime($datetime);
        if (!$ts) return '-';
        $diff = time() - $ts;
        if ($diff < 60)        return $diff . 's ago';
        if ($diff < 3600)      return floor($diff / 60) . 'm ago';
        if ($diff < 86400)     return floor($diff / 3600) . 'h ago';
        if ($diff < 604800)    return floor($diff / 86400) . 'd ago';
        return date('d M Y', $ts);
    }
}

// ---------------------------------------------------------------------
// Random delay (within campaign min/max)
// ---------------------------------------------------------------------
if (!function_exists('campaign_random_delay')) {
    function campaign_random_delay(): int
    {
        $min = max(30, (int) wasend_setting('min_delay_seconds', 120));
        $max = max($min, (int) wasend_setting('max_delay_seconds', 300));
        return random_int($min, $max);
    }
}

// ---------------------------------------------------------------------
// HMAC signature helpers
// ---------------------------------------------------------------------
if (!function_exists('wasend_hmac')) {
    function wasend_hmac(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }
    function wasend_verify_hmac(string $payload, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '') return false;
        $calc = wasend_hmac($payload, $secret);
        return hash_equals($calc, $signature);
    }
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    function csrf_verify(?string $token): bool
    {
        return !empty($token) && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ---------------------------------------------------------------------
// Get JSON body (for POST APIs)
// ---------------------------------------------------------------------
if (!function_exists('read_json_input')) {
    function read_json_input(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

// ---------------------------------------------------------------------
// Pagination helper
// ---------------------------------------------------------------------
if (!function_exists('paginate_args')) {
    function paginate_args(array $req, int $defaultPer = 50, int $maxPer = 200): array
    {
        $page    = max(1, (int) ($req['page'] ?? 1));
        $perPage = max(1, min($maxPer, (int) ($req['per_page'] ?? $defaultPer)));
        $offset  = ($page - 1) * $perPage;
        return ['page' => $page, 'per_page' => $perPage, 'offset' => $offset];
    }
}

// ---------------------------------------------------------------------
// Generate unique short ID
// ---------------------------------------------------------------------
if (!function_exists('short_id')) {
    function short_id(int $len = 12): string
    {
        return substr(bin2hex(random_bytes(16)), 0, $len);
    }
}
