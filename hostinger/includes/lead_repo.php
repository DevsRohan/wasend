<?php
/**
 * lead_repo.php - DB layer for leads
 */
declare(strict_types=1);

class LeadRepo
{
    private PDO $pdo;
    public function __construct() { $this->pdo = wasend_db(); }

    /**
     * Create a lead. Returns inserted id, or existing id on duplicate phone.
     */
    public function upsert(array $row): int
    {
        $sql = 'INSERT INTO leads
            (business_name, address, locality, city, state, phone_number, phone_raw,
             website_url, website_status, rating, review_count, pitch_type,
             language_preference, source, tags, notes)
            VALUES
            (:business_name, :address, :locality, :city, :state, :phone_number, :phone_raw,
             :website_url, :website_status, :rating, :review_count, :pitch_type,
             :language_preference, :source, :tags, :notes)
            ON DUPLICATE KEY UPDATE
                business_name = VALUES(business_name),
                address       = COALESCE(VALUES(address), address),
                locality      = COALESCE(VALUES(locality), locality),
                city          = COALESCE(VALUES(city), city),
                state         = COALESCE(VALUES(state), state),
                website_url   = COALESCE(VALUES(website_url), website_url),
                website_status= VALUES(website_status),
                rating        = COALESCE(VALUES(rating), rating),
                review_count  = VALUES(review_count),
                pitch_type    = VALUES(pitch_type),
                language_preference = VALUES(language_preference),
                tags          = COALESCE(VALUES(tags), tags),
                updated_at    = NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':business_name' => $row['business_name'],
            ':address'       => $row['address'] ?? null,
            ':locality'      => $row['locality'] ?? null,
            ':city'          => $row['city'] ?? null,
            ':state'         => $row['state'] ?? null,
            ':phone_number'  => $row['phone_number'],
            ':phone_raw'     => $row['phone_raw'] ?? null,
            ':website_url'   => $row['website_url'] ?? null,
            ':website_status'=> $row['website_status'] ?? 'unknown',
            ':rating'        => $row['rating'] ?? null,
            ':review_count'  => $row['review_count'] ?? 0,
            ':pitch_type'    => $row['pitch_type'] ?? 'unknown',
            ':language_preference' => $row['language_preference'] ?? 'auto',
            ':source'        => $row['source'] ?? 'csv_import',
            ':tags'          => $row['tags'] ?? null,
            ':notes'         => $row['notes'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id === 0) {
            $sel = $this->pdo->prepare('SELECT id FROM leads WHERE phone_number = ?');
            $sel->execute([$row['phone_number']]);
            $id = (int) ($sel->fetchColumn() ?: 0);
        }
        return $id;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM leads WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPhone(string $phoneE164): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM leads WHERE phone_number = ? LIMIT 1');
        $stmt->execute([$phoneE164]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByJid(string $jid): ?array
    {
        // jid is e.g. 919876543210@c.us
        $phone = preg_replace('/@.*/', '', $jid) ?? '';
        return $this->findByPhone($phone);
    }

    public function listFiltered(array $filters, int $offset, int $perPage): array
    {
        $where = ['1=1'];
        $bind  = [];

        if (!empty($filters['search'])) {
            $where[] = '(business_name LIKE :s OR phone_number LIKE :s OR city LIKE :s OR locality LIKE :s)';
            $bind[':s'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['whatsapp_status'])) {
            $where[] = 'whatsapp_status = :ws';
            $bind[':ws'] = $filters['whatsapp_status'];
        }
        if (!empty($filters['outreach_status'])) {
            $where[] = 'outreach_status = :os';
            $bind[':os'] = $filters['outreach_status'];
        }
        if (!empty($filters['pitch_type'])) {
            $where[] = 'pitch_type = :pt';
            $bind[':pt'] = $filters['pitch_type'];
        }
        if (!empty($filters['city'])) {
            $where[] = 'city = :city';
            $bind[':city'] = $filters['city'];
        }
        if (!empty($filters['state'])) {
            $where[] = 'state = :state';
            $bind[':state'] = $filters['state'];
        }
        if (!empty($filters['has_unread'])) {
            $where[] = 'unread_count > 0';
        }
        if (!empty($filters['pinned'])) {
            $where[] = 'is_pinned = 1';
        }
        if (!empty($filters['tab'])) {
            switch ($filters['tab']) {
                case 'replied':
                    $where[] = "outreach_status = 'replied'"; break;
                case 'pending':
                    $where[] = "outreach_status IN ('pending','queued')"; break;
                case 'invalid':
                    $where[] = "whatsapp_status IN ('invalid','not_on_whatsapp','failed')"; break;
                case 'sent':
                    $where[] = "outreach_status IN ('sent','delivered','read')"; break;
            }
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM leads WHERE $whereSql");
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, business_name, locality, city, state, phone_number, website_status,
                       rating, review_count, whatsapp_status, outreach_status, pitch_type,
                       language_preference, tags, unread_count, is_pinned,
                       last_contacted_at, last_reply_at, updated_at
                FROM leads
                WHERE $whereSql
                ORDER BY is_pinned DESC,
                         (last_reply_at IS NULL),
                         last_reply_at DESC,
                         updated_at DESC
                LIMIT :off, :lim";
        $stmt = $this->pdo->prepare($sql);
        foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return ['total' => $total, 'rows' => $rows];
    }

    public function nextForCampaign(int $campaignId, int $limit = 1): array
    {
        // Find leads that are valid + pending + not replied + not skipped
        $sql = "SELECT l.*
                FROM leads l
                LEFT JOIN campaign_queue q
                       ON q.campaign_id = :cid AND q.lead_id = l.id
                WHERE l.whatsapp_status = 'valid'
                  AND l.outreach_status IN ('pending','queued')
                  AND (q.status IS NULL OR q.status = 'pending')
                ORDER BY l.id ASC
                LIMIT :lim";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':cid', $campaignId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setOutreachStatus(int $leadId, string $status, ?string $contactedAt = null): void
    {
        if ($contactedAt) {
            $stmt = $this->pdo->prepare('UPDATE leads SET outreach_status = ?, last_contacted_at = ? WHERE id = ?');
            $stmt->execute([$status, $contactedAt, $leadId]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE leads SET outreach_status = ? WHERE id = ?');
            $stmt->execute([$status, $leadId]);
        }
    }

    public function setWhatsappStatus(int $leadId, string $status, ?string $jid = null): void
    {
        if ($jid !== null) {
            $stmt = $this->pdo->prepare('UPDATE leads SET whatsapp_status = ?, whatsapp_jid = ? WHERE id = ?');
            $stmt->execute([$status, $jid, $leadId]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE leads SET whatsapp_status = ? WHERE id = ?');
            $stmt->execute([$status, $leadId]);
        }
    }

    public function markReplied(int $leadId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE leads
             SET outreach_status = 'replied',
                 last_reply_at = NOW(),
                 unread_count = unread_count + 1
             WHERE id = ?"
        );
        $stmt->execute([$leadId]);
    }

    public function incrementUnread(int $leadId): void
    {
        $stmt = $this->pdo->prepare('UPDATE leads SET unread_count = unread_count + 1 WHERE id = ?');
        $stmt->execute([$leadId]);
    }

    public function clearUnread(int $leadId): void
    {
        $stmt = $this->pdo->prepare('UPDATE leads SET unread_count = 0 WHERE id = ?');
        $stmt->execute([$leadId]);
    }

    public function update(int $id, array $fields): bool
    {
        $allowed = ['business_name','address','locality','city','state','website_url',
                    'website_status','rating','review_count','pitch_type','language_preference',
                    'tags','notes','is_pinned'];
        $set = [];
        $bind = [':id' => $id];
        foreach ($fields as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $set[] = "$k = :$k";
                $bind[":$k"] = $v;
            }
        }
        if (!$set) return false;
        $sql = 'UPDATE leads SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bind);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM leads WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function stats(): array
    {
        $pdo = $this->pdo;
        $row = $pdo->query("
            SELECT
              COUNT(*) AS total,
              SUM(whatsapp_status = 'valid') AS wa_valid,
              SUM(whatsapp_status = 'invalid' OR whatsapp_status = 'not_on_whatsapp') AS wa_invalid,
              SUM(whatsapp_status = 'pending') AS wa_pending,
              SUM(outreach_status = 'sent' OR outreach_status = 'delivered' OR outreach_status = 'read') AS sent,
              SUM(outreach_status = 'replied') AS replied,
              SUM(outreach_status = 'pending') AS pending_outreach,
              SUM(outreach_status = 'failed') AS failed,
              SUM(unread_count > 0) AS unread_threads,
              SUM(pitch_type = 'A') AS type_a,
              SUM(pitch_type = 'B') AS type_b
            FROM leads
        ")->fetch();

        $today = $pdo->query("
            SELECT COUNT(*) AS sent_today
            FROM messages
            WHERE direction = 'outbound' AND is_first_outreach = 1
              AND DATE(timestamp) = CURDATE()
        ")->fetch();

        return [
            'total'           => (int) ($row['total'] ?? 0),
            'wa_valid'        => (int) ($row['wa_valid'] ?? 0),
            'wa_invalid'      => (int) ($row['wa_invalid'] ?? 0),
            'wa_pending'      => (int) ($row['wa_pending'] ?? 0),
            'sent'            => (int) ($row['sent'] ?? 0),
            'replied'         => (int) ($row['replied'] ?? 0),
            'pending_outreach'=> (int) ($row['pending_outreach'] ?? 0),
            'failed'          => (int) ($row['failed'] ?? 0),
            'unread_threads'  => (int) ($row['unread_threads'] ?? 0),
            'type_a'          => (int) ($row['type_a'] ?? 0),
            'type_b'          => (int) ($row['type_b'] ?? 0),
            'sent_today'      => (int) ($today['sent_today'] ?? 0),
        ];
    }
}
