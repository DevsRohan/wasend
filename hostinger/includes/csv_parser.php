<?php
/**
 * csv_parser.php - Parse + sanitize + import CSV leads.
 *
 * Expected columns (case-insensitive, flexible):
 *   Business Name, Address, Phone, Website, Rating, Reviews, Status,
 *   Locality, City, State, Tags
 */
declare(strict_types=1);

class CsvParser
{
    /**
     * Parse CSV file and return array of normalized lead rows + summary.
     *
     * @return array ['rows'=>array, 'errors'=>array, 'stats'=>array]
     */
    public static function parse(string $filePath): array
    {
        if (!is_file($filePath)) {
            return ['rows' => [], 'errors' => ['file_not_found'], 'stats' => []];
        }
        $fh = @fopen($filePath, 'r');
        if (!$fh) {
            return ['rows' => [], 'errors' => ['cannot_open_file'], 'stats' => []];
        }
        $rows    = [];
        $errors  = [];
        $headers = null;
        $lineNo  = 0;
        $skipped = 0;

        // BOM strip helper
        while (($cells = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            $lineNo++;
            if ($cells === [null] || $cells === [false]) continue;

            if ($headers === null) {
                $cells[0] = ltrim((string) $cells[0], "\xEF\xBB\xBF");
                $headers = array_map(fn($h) => self::normalizeHeader((string) $h), $cells);
                continue;
            }

            // pad cells to header length
            while (count($cells) < count($headers)) $cells[] = '';
            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
            }

            $row = self::mapRow($assoc);
            if (!$row) {
                $skipped++;
                continue;
            }
            $rows[] = $row;
        }
        fclose($fh);

        return [
            'rows'   => $rows,
            'errors' => $errors,
            'stats'  => [
                'total_lines' => $lineNo,
                'parsed'      => count($rows),
                'skipped'     => $skipped,
            ],
        ];
    }

    private static function normalizeHeader(string $h): string
    {
        $h = trim(mb_strtolower($h));
        $h = preg_replace('/[^a-z0-9]+/', '_', $h) ?? '';
        return trim($h, '_');
    }

    private static function pick(array $row, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string) $row[$k]) !== '') {
                return trim((string) $row[$k]);
            }
        }
        return '';
    }

    /**
     * Map normalized CSV row to lead row. Returns null if invalid.
     */
    private static function mapRow(array $row): ?array
    {
        $name  = self::pick($row, ['business_name','name','business','title']);
        $phone = self::pick($row, ['phone','phone_number','mobile','contact','whatsapp']);
        if ($name === '' || $phone === '') return null;

        $address = self::pick($row, ['address','full_address']);
        $locality = self::pick($row, ['locality','area','neighborhood']);
        $city    = self::pick($row, ['city','town']);
        $state   = self::pick($row, ['state','region']);
        $website = self::pick($row, ['website','website_url','url','site']);
        $rating  = self::pick($row, ['rating','stars']);
        $reviews = self::pick($row, ['reviews','review_count','reviews_count','no_of_reviews']);
        $status  = self::pick($row, ['status','website_status']);
        $tags    = self::pick($row, ['tags','category','categories']);

        // If locality/city/state missing, attempt to parse from address
        if (($locality === '' || $city === '' || $state === '') && $address !== '') {
            $parsed = self::parseAddress($address);
            $locality = $locality !== '' ? $locality : $parsed['locality'];
            $city     = $city !== ''     ? $city     : $parsed['city'];
            $state    = $state !== ''    ? $state    : $parsed['state'];
        }

        $phoneNorm = normalize_phone($phone);
        if (!$phoneNorm['valid']) return null;

        $websiteUrl = self::cleanUrl($website);
        $websiteStatus = self::detectWebsiteStatus($websiteUrl, $status);

        $ratingFloat = $rating !== '' ? (float) $rating : null;
        $reviewInt   = $reviews !== '' ? (int) preg_replace('/\D+/', '', $reviews) : 0;

        $pitch = $websiteStatus === 'has_website' ? 'A' : ($websiteStatus === 'no_website' ? 'B' : 'unknown');
        if ($pitch === 'unknown') {
            $pitch = $websiteUrl ? 'A' : 'B';
        }

        return [
            'business_name'  => clean_str($name, 240),
            'address'        => clean_str($address, 1000),
            'locality'       => clean_str($locality, 140),
            'city'           => clean_str($city, 120),
            'state'          => clean_str($state, 120),
            'phone_number'   => $phoneNorm['e164'],
            'phone_raw'      => clean_str($phone, 60),
            'website_url'    => $websiteUrl,
            'website_status' => $websiteStatus,
            'rating'         => ($ratingFloat !== null && $ratingFloat >= 0 && $ratingFloat <= 5) ? $ratingFloat : null,
            'review_count'   => max(0, $reviewInt),
            'pitch_type'     => $pitch,
            'tags'           => $tags !== '' ? clean_str($tags, 480) : null,
            'language_preference' => 'auto',
            'source'         => 'csv_import',
        ];
    }

    private static function cleanUrl(string $url): ?string
    {
        $u = trim($url);
        if ($u === '' || strtolower($u) === 'no website' || strtolower($u) === 'none' || strtolower($u) === 'n/a') {
            return null;
        }
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . ltrim($u, '/');
        }
        if (!filter_var($u, FILTER_VALIDATE_URL)) return null;
        return mb_substr($u, 0, 480);
    }

    private static function detectWebsiteStatus(?string $url, string $status): string
    {
        $s = mb_strtolower(trim($status));
        if (in_array($s, ['no website','no-website','nowebsite','none','no'], true)) return 'no_website';
        if (in_array($s, ['has website','website','yes','active'], true))             return 'has_website';
        if ($url) return 'has_website';
        if ($s === '' && !$url) return 'no_website';
        return 'unknown';
    }

    /**
     * Very lightweight India address parser.
     * Address format example: "Shop 5, Boring Road, Patna, Bihar 800001"
     */
    public static function parseAddress(string $address): array
    {
        $parts = array_map('trim', explode(',', $address));
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));
        $n = count($parts);
        $locality = $city = $state = '';
        if ($n >= 3) {
            $locality = $parts[$n - 3];
            $city     = $parts[$n - 2];
            $state    = preg_replace('/\s*\d{5,7}.*$/', '', $parts[$n - 1]) ?? '';
        } elseif ($n === 2) {
            $city  = $parts[0];
            $state = preg_replace('/\s*\d{5,7}.*$/', '', $parts[1]) ?? '';
        } elseif ($n === 1) {
            $city = $parts[0];
        }
        return [
            'locality' => clean_str($locality, 140),
            'city'     => clean_str($city, 120),
            'state'    => clean_str($state, 120),
        ];
    }

    /**
     * Insert parsed rows into DB. Returns counts.
     */
    public static function importToDb(array $rows): array
    {
        $repo = new LeadRepo();
        $inserted = 0;
        $duplicates = 0;
        foreach ($rows as $r) {
            try {
                $existing = $repo->findByPhone($r['phone_number']);
                if ($existing) { $duplicates++; }
                $id = $repo->upsert($r);
                if (!$existing && $id > 0) $inserted++;
            } catch (Throwable $e) {
                wasend_log('error', 'csv_import', 'row_failed', ['err' => $e->getMessage(), 'phone' => $r['phone_number'] ?? null]);
            }
        }
        return ['inserted' => $inserted, 'duplicates' => $duplicates, 'total' => count($rows)];
    }
}
