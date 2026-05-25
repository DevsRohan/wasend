<?php
/**
 * groq.php - Groq AI prompt builder + client + fallback engine.
 *
 * Responsibilities:
 *   - Build personalized prompt
 *   - Call Groq REST API
 *   - Fallback template generator if API fails
 */
declare(strict_types=1);

class Groq
{
    private string $apiKey;
    private string $model;
    private string $fallbackModel;
    private int    $maxTokens;
    private float  $temperature;

    public function __construct()
    {
        $this->apiKey        = (string) wasend_setting('groq_api_key', '');
        $this->model         = (string) wasend_setting('groq_model', 'llama-3.3-70b-versatile');
        $this->fallbackModel = (string) wasend_setting('groq_fallback_model', 'llama-3.1-8b-instant');
        $this->maxTokens     = (int)    wasend_setting('groq_max_tokens', 700);
        $this->temperature   = (float)  wasend_setting('groq_temperature', 0.75);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && strpos($this->apiKey, 'gsk_') === 0;
    }

    /**
     * Generate personalized first-outreach message.
     *
     * @param array $ctx Output of Personalization::buildContext()
     * @return array ['ok'=>bool, 'message'=>string, 'source'=>'groq'|'fallback', 'error'=>?string]
     */
    public function generateOutreach(array $ctx): array
    {
        $prompt = $this->buildPrompt($ctx);

        if (!$this->isConfigured()) {
            return [
                'ok'      => true,
                'message' => $this->fallbackTemplate($ctx),
                'source'  => 'fallback',
                'error'   => 'groq_not_configured',
            ];
        }

        // Try primary model
        $res = $this->callGroq($prompt['system'], $prompt['user'], $this->model);
        if ($res['ok']) {
            return ['ok' => true, 'message' => $res['text'], 'source' => 'groq', 'error' => null];
        }

        // Try fallback model
        if ($this->fallbackModel && $this->fallbackModel !== $this->model) {
            $res2 = $this->callGroq($prompt['system'], $prompt['user'], $this->fallbackModel);
            if ($res2['ok']) {
                return ['ok' => true, 'message' => $res2['text'], 'source' => 'groq_fallback', 'error' => null];
            }
        }

        return [
            'ok'      => true,
            'message' => $this->fallbackTemplate($ctx),
            'source'  => 'fallback',
            'error'   => $res['error'] ?? 'groq_failed',
        ];
    }

    /**
     * Build a structured prompt.
     */
    public function buildPrompt(array $ctx): array
    {
        $langInstruction = $this->languageInstruction($ctx['language']);
        $services = $ctx['services'] ?: ['Business Websites'];
        $servicesList = implode(' and ', array_map(fn($s) => "**$s**", $services));

        $hasWebsite = $ctx['pitch_type'] === 'A';
        $pitchAngle = $hasWebsite
            ? "They already have a website. Focus on conversion optimization, automation, AI agents, customer engagement, and growth — NOT on building a website."
            : "They do NOT have a website. Focus on digital presence, online discoverability, customer trust, business website / landing page, and digital marketing.";

        $rating = $ctx['rating'] !== null ? number_format((float) $ctx['rating'], 1) : null;
        $reviewLine = $rating
            ? "Rating: $rating with {$ctx['review_count']} reviews"
            : "Reviews: {$ctx['review_count']}";

        $location = trim(implode(', ', array_filter([$ctx['locality'], $ctx['city'], $ctx['state']])));

        $system = <<<SYS
You are a senior B2B outreach copywriter for a digital agency. You write the FIRST WhatsApp message
to a small/medium local business owner. The message must feel hand-crafted, observant, and human —
NEVER like a template, NEVER spammy, NEVER pushy.

HARD RULES:
- 4 to 5 short paragraphs only.
- No pricing. No fake urgency. No emojis spam (max 1 subtle emoji or none).
- No "Hi, I am from XYZ company". Be warmer and more local.
- Do not list more than two services. Pick only what genuinely fits this lead.
- Mention the business by name. Mention locality / city naturally.
- Sound like a real person typing on WhatsApp, not a marketing email.
- End with a soft, low-pressure question or CTA. Do NOT ask for a meeting hard.
- Never mention "AI generated" or that this is automated.
- Output ONLY the message text. No preface. No explanation.

LANGUAGE STYLE:
$langInstruction
SYS;

        $user = <<<USR
Write the first WhatsApp outreach message for this lead.

Business: {$ctx['business_name']}
Location: $location
$reviewLine
Website status: {$ctx['website_status']}
Pitch angle: $pitchAngle
Services to subtly mention (max 2): $servicesList
Sender / agency name: {$ctx['owner_name']}

Structure:
1) Local trust observation (e.g. mention business name, locality, that you noticed them).
2) Digital observation (something specific to their website / no-website / reviews / rating).
3) Tailored opportunity (why a small upgrade could help them).
4) Soft service mention (1-2 services only, woven naturally).
5) Soft CTA — friendly, low pressure, ask if they'd be open to a quick chat.

Now write the WhatsApp message.
USR;

        return ['system' => $system, 'user' => $user];
    }

    private function languageInstruction(string $lang): string
    {
        switch ($lang) {
            case 'hinglish':
                return "Use natural Hinglish (Roman script) — friendly Hindi-English mix that local UP / Bihar / Delhi business owners actually speak on WhatsApp. Casual, respectful, never robotic.";
            case 'gujarati_mix':
                return "Use a Gujarati-friendly conversational English mix. Light, warm, business-respectful. Sprinkle 1-2 Gujarati words naturally if they fit (e.g., 'kem cho', 'maja avi gayi') — but stay readable.";
            case 'marathi_mix':
                return "Use a Marathi-friendly conversational English mix. Warm, respectful, slightly local Maharashtra vibe. Light tone. May include 1 light Marathi phrase like 'kasa kay' if natural.";
            case 'en_in':
            default:
                return "Use simple Indian-English business tone. Conversational, warm, never overly formal. Avoid jargon.";
        }
    }

    /**
     * Make raw Groq API call.
     */
    private function callGroq(string $system, string $user, string $model): array
    {
        $url = 'https://api.groq.com/openai/v1/chat/completions';
        $payload = [
            'model'       => $model,
            'temperature' => $this->temperature,
            'max_tokens'  => $this->maxTokens,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            wasend_log('error', 'groq', 'curl_error', ['err' => $err]);
            return ['ok' => false, 'error' => 'curl_error: ' . $err];
        }
        if ($code < 200 || $code >= 300) {
            wasend_log('error', 'groq', 'http_error', ['code' => $code, 'resp' => mb_substr((string) $resp, 0, 500)]);
            return ['ok' => false, 'error' => 'http_' . $code];
        }
        $data = json_decode((string) $resp, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        $text = trim((string) $text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'empty_response'];
        }
        // Strip wrapping quotes if any
        if (preg_match('/^"(.*)"$/s', $text, $m)) $text = $m[1];
        return ['ok' => true, 'text' => $text];
    }

    /**
     * Deterministic but personalized fallback message (used if Groq is unavailable).
     */
    public function fallbackTemplate(array $ctx): string
    {
        $name     = $ctx['business_name'] ?: 'aapki business';
        $loc      = $ctx['locality'] ?: ($ctx['city'] ?: '');
        $owner    = $ctx['owner_name'] ?: 'hum';
        $services = $ctx['services'] ?: ['Business Websites', 'Digital Marketing'];
        $svc      = implode(' aur ', array_slice($services, 0, 2));
        $hasWeb   = $ctx['pitch_type'] === 'A';

        if ($ctx['language'] === 'hinglish') {
            $obs = $hasWeb
                ? "$name ka online presence dekha — website thik hai, par aaj ke time me bahut sare local businesses ke liye conversion aur engagement layer missing hota hai."
                : "$name ke baare me $loc area me kaafi acha sun ne ko mila, par online search me proper website nahi mili.";
            $opp = $hasWeb
                ? "Thoda sa optimization aur smart automation laga ke aap easily zyada enquiries convert kar sakte ho — same traffic se, bina extra ad spend ke."
                : "Ek simple business website ya landing page se aapki credibility kaafi badh sakti hai aur Google par bhi naye customers tak pahunch banti hai.";
            $cta = "Agar interest ho to ek chhota sa idea WhatsApp pe share kar du? 2 min me samajh aa jayega.";
            return implode("\n\n", [
                "Namaste, $name team 🙏",
                $obs,
                $opp,
                "Hum {$owner} ki taraf se mostly $svc handle karte hain — sirf wahi solution bolte hain jo aapke business ke liye sach me kaam aaye.",
                $cta,
            ]);
        }

        if ($ctx['language'] === 'gujarati_mix') {
            $obs = $hasWeb
                ? "Saw $name's online presence — website is set, but most local businesses today miss the layer for conversion and customer engagement."
                : "$name about $loc area — heard good things, but couldn't find a proper website online.";
            return implode("\n\n", [
                "Hello $name team, kem cho!",
                $obs,
                $hasWeb
                    ? "A small optimization + smart automation can help you convert more from the same traffic — without extra ad spend."
                    : "A simple business website / landing page can really boost trust and bring new customers from Google.",
                "From {$owner}, we mainly handle $svc — and we only suggest what genuinely fits your business.",
                "Open to a quick chat? I'll share a small idea in 2 minutes.",
            ]);
        }

        if ($ctx['language'] === 'marathi_mix') {
            return implode("\n\n", [
                "Hello $name team, kasa kay!",
                $hasWeb
                    ? "Saw your website — looking decent, but conversion + automation layer is missing for most local businesses."
                    : "Heard about $name in $loc area, but couldn't find a proper website online.",
                $hasWeb
                    ? "A small upgrade with smart automation can convert more visitors from the same traffic — no extra ad cost."
                    : "A simple business website can really lift trust and bring new customers from Google.",
                "From {$owner}, we focus on $svc — only suggesting what genuinely fits your business.",
                "Open to a quick chat? Will share a small idea in 2 minutes.",
            ]);
        }

        // en_in fallback
        return implode("\n\n", [
            "Hello $name team,",
            $hasWeb
                ? "I came across your business online — your website is up, but I noticed the conversion + automation layer is something most local businesses underuse."
                : "I came across $name in $loc — you have good word-of-mouth, but I couldn't find a proper website online.",
            $hasWeb
                ? "A small optimization plus smart automation could meaningfully improve enquiries from the same traffic, without extra ad spend."
                : "A simple business website or landing page could lift your credibility and bring fresh customers from Google searches.",
            "From {$owner}, we mainly handle $svc — and we only suggest what genuinely fits your business.",
            "Would you be open to a quick 2-minute chat? Happy to share a small idea.",
        ]);
    }
}
