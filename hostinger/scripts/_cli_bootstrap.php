<?php
/**
 * _cli_bootstrap.php - Common CLI bootstrap for cron / shell scripts.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // Allow web execution only with secret query param (for hosts without CLI cron)
    require_once __DIR__ . '/../config/app.php';
    $expected = (string) wasend_setting('webhook_secret', '');
    $given    = $_GET['key'] ?? '';
    if ($expected === '' || !hash_equals($expected, (string) $given)) {
        http_response_code(403);
        echo "forbidden";
        exit;
    }
} else {
    require_once __DIR__ . '/../config/app.php';
}

require_once __DIR__ . '/../config/db.php';

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
    if (isset($map[$class])) require_once WASEND_INCLUDES . $map[$class];
});

function cli_log(string $msg): void
{
    $ts = date('H:i:s');
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, "[$ts] $msg\n");
    } else {
        echo "[$ts] " . htmlspecialchars($msg) . "<br>\n";
        @ob_flush(); @flush();
    }
}
