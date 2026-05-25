<?php
/**
 * personalization.php - Decides pitch type, language, and relevant services per lead.
 */
declare(strict_types=1);

class Personalization
{
    /** Map of state (lowercased) => language preference */
    public const LANG_MAP = [
        'bihar'         => 'hinglish',
        'jharkhand'     => 'hinglish',
        'uttar pradesh' => 'hinglish',
        'delhi'         => 'hinglish',
        'haryana'       => 'hinglish',
        'punjab'        => 'hinglish',
        'rajasthan'     => 'hinglish',
        'madhya pradesh'=> 'hinglish',
        'chhattisgarh'  => 'hinglish',
        'uttarakhand'   => 'hinglish',
        'gujarat'       => 'gujarati_mix',
        'maharashtra'   => 'marathi_mix',
        'goa'           => 'marathi_mix',
        'karnataka'     => 'en_in',
        'kerala'        => 'en_in',
        'tamil nadu'    => 'en_in',
        'telangana'     => 'en_in',
        'andhra pradesh'=> 'en_in',
        'west bengal'   => 'en_in',
        'odisha'        => 'en_in',
        'assam'         => 'en_in',
    ];

    public const ALL_SERVICES = [
        'Landing Pages',
        'Business Websites',
        'eCommerce Websites',
        'Custom Web Apps',
        'AI Agents',
        'Automation Systems',
        'Android Apps',
        'Chrome Extensions',
        'Digital Marketing',
    ];

    /**
     * Decide pitch_type from website_url + website_status
     */
    public static function decidePitchType(?string $websiteUrl, ?string $websiteStatus): string
    {
        if ($websiteStatus === 'has_website') return 'A';
        if ($websiteStatus === 'no_website')  return 'B';
        if ($websiteUrl && trim($websiteUrl) !== '' && strtolower($websiteUrl) !== 'no website') {
            return 'A';
        }
        return 'B';
    }

    /**
     * Decide language preference from state.
     */
    public static function decideLanguage(?string $state, string $default = 'hinglish'): string
    {
        if (!$state) return $default;
        $key = mb_strtolower(trim($state));
        return self::LANG_MAP[$key] ?? $default;
    }

    /**
     * Pick 1-2 most relevant services for this lead.
     * Logic:
     *   Type A (has website): focus on optimization side
     *      - low rating  -> Conversion + Digital Marketing
     *      - high rating -> Automation + AI Agents
     *      - default     -> AI Agents + Automation Systems
     *   Type B (no website): focus on building presence
     *      - has reviews     -> Business Website + Digital Marketing
     *      - no reviews      -> Landing Page + Digital Marketing
     */
    public static function pickServices(string $pitchType, ?float $rating, int $reviewCount, array $allServices): array
    {
        $services = $allServices ?: self::ALL_SERVICES;

        if ($pitchType === 'A') {
            if ($rating !== null && $rating < 4.0) {
                return array_values(array_intersect($services, ['Digital Marketing', 'AI Agents']));
            }
            if ($rating !== null && $rating >= 4.5) {
                return array_values(array_intersect($services, ['Automation Systems', 'AI Agents']));
            }
            return array_values(array_intersect($services, ['AI Agents', 'Automation Systems']));
        }

        // Type B
        if ($reviewCount > 30) {
            return array_values(array_intersect($services, ['Business Websites', 'Digital Marketing']));
        }
        return array_values(array_intersect($services, ['Landing Pages', 'Digital Marketing']));
    }

    /**
     * Build a structured "context object" for groq.php prompt builder.
     */
    public static function buildContext(array $lead, array $owner): array
    {
        $allServices = is_array($owner['services'] ?? null)
            ? $owner['services']
            : array_filter(array_map('trim', explode(',', (string) ($owner['services'] ?? ''))));

        if (empty($allServices)) $allServices = self::ALL_SERVICES;

        $pitch    = self::decidePitchType($lead['website_url'] ?? null, $lead['website_status'] ?? null);
        $lang     = self::decideLanguage($lead['state'] ?? null);
        $services = self::pickServices($pitch, isset($lead['rating']) ? (float) $lead['rating'] : null, (int) ($lead['review_count'] ?? 0), $allServices);

        return [
            'business_name' => (string) ($lead['business_name'] ?? 'Business'),
            'locality'      => (string) ($lead['locality'] ?? ''),
            'city'          => (string) ($lead['city'] ?? ''),
            'state'         => (string) ($lead['state'] ?? ''),
            'rating'        => $lead['rating'] !== null ? (float) $lead['rating'] : null,
            'review_count'  => (int) ($lead['review_count'] ?? 0),
            'website_status'=> (string) ($lead['website_status'] ?? 'unknown'),
            'pitch_type'    => $pitch,
            'language'      => $lang,
            'services'      => array_values(array_slice($services, 0, 2)),
            'owner_name'    => (string) ($owner['name'] ?? 'Our team'),
        ];
    }
}
