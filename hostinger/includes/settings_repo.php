<?php
/**
 * settings_repo.php - DB layer for settings KV store
 */
declare(strict_types=1);

class SettingsRepo
{
    private PDO $pdo;
    public function __construct() { $this->pdo = wasend_db(); }

    public function all(bool $maskSecrets = true): array
    {
        $rows = $this->pdo->query('SELECT * FROM settings ORDER BY category, setting_key')->fetchAll();
        foreach ($rows as &$r) {
            if ($r['is_secret'] && $r['setting_value'] !== '' && $r['setting_value'] !== null) {
                $r['has_value']     = true;
                $r['setting_value'] = $maskSecrets ? '••••••••' : wasend_decrypt((string) $r['setting_value']);
            } else {
                $r['has_value'] = !empty($r['setting_value']);
            }
        }
        return $rows;
    }

    public function get(string $key)
    {
        $stmt = $this->pdo->prepare('SELECT setting_value, value_type, is_secret FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $val = $row['setting_value'];
        if ($row['is_secret'] && $val !== null && $val !== '') {
            $val = wasend_decrypt((string) $val);
        }
        return wasend_cast_setting($val, $row['value_type']);
    }

    public function set(string $key, $value): bool
    {
        $stmt = $this->pdo->prepare('SELECT value_type, is_secret FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $meta = $stmt->fetch();

        $type     = $meta['value_type'] ?? 'string';
        $isSecret = (int) ($meta['is_secret'] ?? 0) === 1;

        // Coerce
        if ($type === 'bool')  $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        elseif ($type === 'int')   $value = (string) (int) $value;
        elseif ($type === 'float') $value = (string) (float) $value;
        elseif ($type === 'json')  $value = is_string($value) ? $value : json_encode($value);
        else                       $value = (string) $value;

        if ($isSecret && $value !== '') {
            $value = wasend_encrypt($value);
        }

        if ($meta) {
            $up = $this->pdo->prepare('UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?');
            return $up->execute([$value, $key]);
        }
        // Auto-create unknown key as string
        $ins = $this->pdo->prepare('INSERT INTO settings (setting_key, setting_value, value_type, category) VALUES (?, ?, "string", "general")');
        return $ins->execute([$key, $value]);
    }

    public function bulkSet(array $kv): int
    {
        $count = 0;
        foreach ($kv as $k => $v) {
            // Skip masked values
            if (is_string($v) && $v === '••••••••') continue;
            if ($this->set($k, $v)) $count++;
        }
        return $count;
    }
}
