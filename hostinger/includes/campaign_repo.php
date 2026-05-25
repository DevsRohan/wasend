<?php
/**
 * campaign_repo.php - DB layer for campaigns + queue
 */
declare(strict_types=1);

class CampaignRepo
{
    private PDO $pdo;
    public function __construct() { $this->pdo = wasend_db(); }

    public function getDefault(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM campaigns ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM campaigns WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaigns SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function incrementSent(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE campaigns
             SET sent_today = sent_today + 1,
                 total_sent = total_sent + 1,
                 last_sent_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    public function incrementFailed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaigns SET total_failed = total_failed + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function incrementReplied(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaigns SET total_replied = total_replied + 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function resetDailyCounter(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaigns SET sent_today = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function update(int $id, array $fields): bool
    {
        $allowed = ['name','description','status','daily_limit','min_delay_seconds',
                    'max_delay_seconds','filters_json','next_run_at'];
        $set = [];
        $bind = [':id' => $id];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $set[] = "$k = :$k";
                $bind[":$k"] = $v;
            }
        }
        if (!$set) return false;
        $sql = 'UPDATE campaigns SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bind);
    }

    /**
     * Add lead to campaign queue
     */
    public function enqueue(int $campaignId, int $leadId, ?string $scheduledAt = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO campaign_queue (campaign_id, lead_id, status, scheduled_at)
             VALUES (?, ?, "pending", ?)
             ON DUPLICATE KEY UPDATE
                scheduled_at = VALUES(scheduled_at),
                status = IF(status IN ("sent","skipped","blocked"), status, "pending")'
        );
        $stmt->execute([$campaignId, $leadId, $scheduledAt]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $sel = $this->pdo->prepare('SELECT id FROM campaign_queue WHERE campaign_id = ? AND lead_id = ?');
            $sel->execute([$campaignId, $leadId]);
            $id = (int) ($sel->fetchColumn() ?: 0);
        }
        return $id;
    }

    public function markQueueSent(int $queueId, int $messageId): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaign_queue SET status = "sent", processed_at = NOW(), message_id = ? WHERE id = ?');
        $stmt->execute([$messageId, $queueId]);
    }

    public function markQueueFailed(int $queueId, string $error): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaign_queue SET status = "failed", attempts = attempts + 1, last_error = ?, processed_at = NOW() WHERE id = ?');
        $stmt->execute([$error, $queueId]);
    }

    public function markQueueSkipped(int $queueId, string $reason): void
    {
        $stmt = $this->pdo->prepare('UPDATE campaign_queue SET status = "skipped", last_error = ?, processed_at = NOW() WHERE id = ?');
        $stmt->execute([$reason, $queueId]);
    }

    public function blockQueueForLead(int $leadId): void
    {
        // Lead replied or invalid - block all pending queue items for this lead
        $stmt = $this->pdo->prepare(
            "UPDATE campaign_queue SET status = 'blocked', last_error = 'lead replied or invalid' WHERE lead_id = ? AND status = 'pending'"
        );
        $stmt->execute([$leadId]);
    }

    public function queueState(int $campaignId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(status = 'pending')  AS pending,
                SUM(status = 'sent')     AS sent,
                SUM(status = 'failed')   AS failed,
                SUM(status = 'skipped')  AS skipped,
                SUM(status = 'blocked')  AS blocked,
                COUNT(*) AS total
             FROM campaign_queue WHERE campaign_id = ?"
        );
        $stmt->execute([$campaignId]);
        $row = $stmt->fetch() ?: [];
        return [
            'pending' => (int)($row['pending'] ?? 0),
            'sent'    => (int)($row['sent'] ?? 0),
            'failed'  => (int)($row['failed'] ?? 0),
            'skipped' => (int)($row['skipped'] ?? 0),
            'blocked' => (int)($row['blocked'] ?? 0),
            'total'   => (int)($row['total'] ?? 0),
        ];
    }
}
