<?php
/**
 * _bootstrap.php - Common bootstrap for all API endpoints.
 * Loads config, auth, and enforces CSRF for POST.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Auto-load any repos when used
spl_autoload_register(function ($class) {
    $map = [
        'LeadRepo'        => '/lead_repo.php',
        'MessageRepo'     => '/message_repo.php',
        'CampaignRepo'    => '/campaign_repo.php',
        'SettingsRepo'    => '/settings_repo.php',
        'LogRepo'         => '/log_repo.php',
        'Personalization' => '/personalization.php',
        'Groq'            => '/groq.php',
        'NodeClient'      => '/node_client.php',
        'CsvParser'       => '/csv_parser.php',
    ];
    if (isset($map[$class])) {
        $path = WASEND_INCLUDES . $map[$class];
        if (is_file($path)) require_once $path;
    }
});

/**
 * Enforce auth + CSRF for non-GET API requests.
 * GET endpoints still require auth but skip CSRF.
 */
function api_guard(bool $requireCsrf = true): array
{
    $user = auth_check(true);
    if ($requireCsrf && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        if (!csrf_verify($token)) {
            json_error('csrf_invalid', 419);
        }
    }
    return $user;
}
