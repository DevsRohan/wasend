<?php
/**
 * message_repo.php - DB layer for messages
 */
declare(strict_types=1);

class MessageRepo
{
    private PDO $pdo;
    public function __construct() { $this->pdo = wasend_db(); }

    public function insertOutbound(int $leadId, string $text, ?string $waMessageId, bool $isFirstOutreach, string $sender = 'system', array $meta = []): int
    {
        // Dedup by wa_message_id if provided
        if ($waMessageId) {
            $sel = $this->pdo->prepare('SELECT id FROM messages WHERE wa_message_id = ? LIMIT 1');
            $sel->execute([$waMessageId]);
            $existing = $sel->fetchColumn();
            if ($existing) return (int) $existing;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO messages
                (lead_id, wa_message_id, direction, sender, message_text, message_type,
                 status, is_first_outreach, meta_json, timestamp)
             VALUES (?, ?, 'outbound', ?, ?, 'text', 'sent', ?, ?, NOW())"
        );
        $stmt->execute([
            $leadId,
            $waMessageId,
            $sender,
            $text,
            $isFirstOutreach ? 1 : 0,
            $meta ? json_encode($meta) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function insertInbound(int $leadId, string $text, ?string $waMessageId, string $messageType = 'text', array $meta = []): int
    {
        if ($waMessageId) {
            $sel = $this->pdo->prepare('SELECT id FROM messages WHERE wa_message_id = ? LIMIT 1');
            $sel->execute([$waMessageId]);
            $existing = $sel->fetchColumn();
            if ($existing) return (int) $existing;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO messages
                (lead_id, wa_message_id, direction, sender, message_text, message_type,
                 status, is_read, meta_json, timestamp)
             VALUES (?, ?, 'inbound', 'lead', ?, ?, 'delivered', 0, ?, NOW())"
        );
        $stmt->execute([
            $leadId,
            $waMessageId,
            $text,
            $messageType,
            $meta ? json_encode($meta) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listByLead(int $leadId, int $limit = 200): array
    {
        $sql = 'SELECT * FROM messages WHERE lead_id = :lid ORDER BY timestamp ASC, id ASC LIMIT :lim';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lid', $leadId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markReadForLead(int $leadId): int
    {
        $stmt = $this->pdo->prepare("UPDATE messages SET is_read = 1 WHERE lead_id = ? AND direction = 'inbound' AND is_read = 0");
        $stmt->execute([$leadId]);
        return $stmt->rowCount();
    }

    public function updateStatusByWaId(string $waMessageId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET status = ? WHERE wa_message_id = ?');
        return $stmt->execute([$status, $waMessageId]);
    }

    public function markFailed(int $messageId, string $error): bool
    {
        $stmt = $this->pdo->prepare("UPDATE messages SET status = 'failed', error_message = ? WHERE id = ?");
        return $stmt->execute([$error, $messageId]);
    }

    public function findByWaId(string $waMessageId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM messages WHERE wa_message_id = ? LIMIT 1');
        $stmt->execute([$waMessageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function recentActivity(int $limit = 25): array
    {
        $sql = 'SELECT m.id, m.lead_id, m.direction, m.message_text, m.timestamp, m.status,
                       l.business_name, l.phone_number
                FROM messages m
                JOIN leads l ON l.id = m.lead_id
                ORDER BY m.id DESC
                LIMIT :lim';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
