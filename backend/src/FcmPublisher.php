<?php
declare(strict_types=1);

final class FcmPublisher
{
    private array $serviceAccount;
    private string $topic;

    public function __construct(string $serviceAccountFile, string $topic = 'hardwire-events')
    {
        if (!is_file($serviceAccountFile) || !is_readable($serviceAccountFile)) {
            throw new RuntimeException('Firebase service account file not found or not readable.');
        }

        $json = file_get_contents($serviceAccountFile);
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)
            || empty($decoded['project_id'])
            || empty($decoded['private_key'])
            || empty($decoded['client_email'])) {
            throw new RuntimeException('Invalid Firebase service account JSON.');
        }

        $this->serviceAccount = $decoded;
        $this->topic = trim($topic) !== '' ? trim($topic) : 'hardwire-events';
    }

    public function sendEvent(array $event): array
    {
        $accessToken = $this->getAccessToken();
        $projectId = (string)$this->serviceAccount['project_id'];
        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';

        $data = [
            'event_id' => (string)($event['event_id'] ?? ''),
            'timestamp' => (string)($event['timestamp'] ?? date('d/m/Y H:i:s')),
            'client' => (string)($event['client'] ?? 'Hardwire'),
            'status' => (string)($event['status'] ?? 'NOVO EVENTO'),
            'priority' => (string)($event['priority'] ?? 'INFO'),
        ];

        $payload = [
            'message' => [
                'topic' => $this->topic,
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                    'ttl' => '60s',
                ],
            ],
        ];

        [$httpCode, $body] = $this->request(
            $url,
            (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=UTF-8',
            ]
        );

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("FCM returned HTTP {$httpCode}: {$body}");
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    public function verifyCredentials(): array
    {
        $token = $this->getAccessToken();
        return [
            'ok' => $token !== '',
            'project_id' => (string)$this->serviceAccount['project_id'],
            'client_email' => (string)$this->serviceAccount['client_email'],
        ];
    }

    private function getAccessToken(): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $tokenUri = (string)($this->serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token');
        $claims = [
            'iss' => (string)$this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsigned = $this->base64Url((string)json_encode($header))
            . '.' . $this->base64Url((string)json_encode($claims));
        $signature = '';
        $ok = openssl_sign(
            $unsigned,
            $signature,
            (string)$this->serviceAccount['private_key'],
            OPENSSL_ALGO_SHA256
        );
        if (!$ok) {
            throw new RuntimeException('Unable to sign Google OAuth JWT.');
        }

        $jwt = $unsigned . '.' . $this->base64Url($signature);
        $postBody = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        [$httpCode, $body] = $this->request(
            $tokenUri,
            $postBody,
            ['Content-Type: application/x-www-form-urlencoded']
        );
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Google OAuth returned HTTP {$httpCode}: {$body}");
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded) || empty($decoded['access_token'])) {
            throw new RuntimeException('Google OAuth response did not include access_token.');
        }

        return (string)$decoded['access_token'];
    }

    private function request(string $url, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return [$httpCode, (string)$response];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
