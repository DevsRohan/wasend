<?php
/**
 * node_client.php - HTTP client wrapper for Hugging Face Node backend
 */
declare(strict_types=1);

class NodeClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) wasend_setting('node_api_url', ''), '/');
        $this->apiKey  = (string) wasend_setting('node_api_key', '');
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '';
    }

    /**
     * Send WhatsApp message via Node engine.
     */
    public function sendMessage(string $jid, string $text, array $extra = []): array
    {
        return $this->request('POST', '/send-message', array_merge([
            'jid'  => $jid,
            'text' => $text,
        ], $extra));
    }

    /**
     * Check if a phone number is on WhatsApp.
     */
    public function checkNumber(string $phoneE164): array
    {
        return $this->request('POST', '/check-number', ['phone' => $phoneE164]);
    }

    public function getStatus(): array
    {
        return $this->request('GET', '/status');
    }

    public function getQr(): array
    {
        return $this->request('GET', '/qr');
    }

    public function restartSession(): array
    {
        return $this->request('POST', '/session/restart', []);
    }

    public function health(): array
    {
        return $this->request('GET', '/health');
    }

    /**
     * Generic JSON request to Node.
     */
    private function request(string $method, string $path, ?array $data = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'node_not_configured'];
        }
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            wasend_log('error', 'node_client', 'curl_error', ['url' => $url, 'err' => $err]);
            return ['ok' => false, 'error' => 'curl_error: ' . $err, 'http' => 0];
        }

        $decoded = json_decode((string) $resp, true);
        $isOk = ($code >= 200 && $code < 300);
        if (!is_array($decoded)) {
            return ['ok' => $isOk, 'raw' => $resp, 'http' => $code];
        }
        if (!isset($decoded['ok'])) $decoded['ok'] = $isOk;
        $decoded['http'] = $code;
        if (!$isOk) {
            wasend_log('warning', 'node_client', 'http_error', ['url' => $url, 'code' => $code, 'resp' => mb_substr((string) $resp, 0, 500)]);
        }
        return $decoded;
    }
}
