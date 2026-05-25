<?php
/**
 * log_repo.php - Activity log queries
 */
declare(strict_types=1);

class LogRepo
{
    private PDO $pdo;
    public function __construct() { $this->pdo = wasend_db(); }

    public function listRecent(int $limit = 100, ?string $level = null, ?string $category = null): array
    {
        $where = '1=1';
        $bind  = [];
        if ($level)    { $where .= ' AND level = :level';     $bind[':level'] = $level; }
        if ($category) { $where .= ' AND category = :cat';    $bind[':cat']   = $category; }

        $sql  = "SELECT * FROM activity_log WHERE $where ORDER BY id DESC LIMIT :lim";
        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function purgeOlderThan(int $days = 30): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)');
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    public function categories(): array
    {
        return $this->pdo->query('SELECT DISTINCT category FROM activity_log ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }
}
