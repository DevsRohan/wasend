<?php
/**
 * import_csv.php - CLI bulk CSV importer
 *
 * Usage:
 *   php scripts/import_csv.php /path/to/leads.csv
 */
declare(strict_types=1);
require_once __DIR__ . '/_cli_bootstrap.php';

$file = $argv[1] ?? null;
if (PHP_SAPI !== 'cli') {
    $file = $_GET['file'] ?? null;
}

if (!$file) {
    cli_log('Usage: php scripts/import_csv.php <path-to-csv>');
    exit(1);
}

if (!is_file($file)) {
    cli_log("File not found: $file");
    exit(1);
}

cli_log("Parsing $file ...");
$parsed = CsvParser::parse($file);
cli_log("Total lines: {$parsed['stats']['total_lines']}");
cli_log("Parsed valid rows: {$parsed['stats']['parsed']}");
cli_log("Skipped rows: {$parsed['stats']['skipped']}");

if (empty($parsed['rows'])) {
    cli_log('No valid rows. Exiting.');
    exit(0);
}

cli_log('Importing into database...');
$res = CsvParser::importToDb($parsed['rows']);
cli_log("Inserted new: {$res['inserted']}");
cli_log("Duplicates updated: {$res['duplicates']}");
cli_log("Total processed: {$res['total']}");

wasend_log('info', 'csv_import_cli', 'completed', $res + ['file' => basename($file)]);
exit(0);
