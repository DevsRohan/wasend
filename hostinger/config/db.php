<?php
/**
 * db.php - PDO singleton for MySQL
 * Hostinger shared-hosting safe; persistent connections off.
 */
declare(strict_types=1);

if (!function_exists('wasend_db')) {
    /**
     * @return PDO
     */
    function wasend_db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log but never expose creds in response
            @error_log('[wasend_db] connection failed: ' . $e->getMessage());
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'database_unavailable']);
                exit;
            }
            throw new RuntimeException('database_unavailable');
        }
        return $pdo;
    }
}
