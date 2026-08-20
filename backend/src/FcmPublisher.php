<?php
declare(strict_types=1);

final class FcmPublisher
{
    private array $serviceAccount;

    public function __construct(
        string $serviceAccountFile,
        private readonly string $topic = 'hardwire-events'
    ) {
        if (!is_file($serviceAccountFile)) {
            throw new RuntimeException('Firebase service account file not found.');
        }
        $json = file_get_contents($serviceAccountFile);
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded) || empty($decoded['project_id']) || empty($decoded['private_key']) || empty($decoded['client_email'])) {
            throw new RuntimeException('Invalid Firebase service account JSON.');
        }
        $this->serviceAccount = $decoded;
    }

    public function sendEvent(array $event): array
    {
        $accessToken = $this->getAccessToken();
        $projectId = $this->serviceAccount['project_id'];
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
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=UTF-8',
            ]
        );

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("FCM returned HTTP {$httpCode}: {$body}");
        }

        return json_decode($body, true) ?: ['raw' => $body];
    }

    private function getAccessToken(): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $this->serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsigned = $this->base64Url(json_encode($header)) . '.' . $this->base64Url(json_encode($claims));
        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $this->serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('Unable to sign Google OAuth JWT.');
        }
        $jwt = $unsigned . '.' . $this->base64Url($signature);

        $tokenUri = $this->serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';
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
        if (empty($decoded['access_token'])) {
            throw new RuntimeException('Google OAuth response did not include access_token.');
        }
        return $decoded['access_token'];
    }

    private function request(string $url, string $body, array $headers): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
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
