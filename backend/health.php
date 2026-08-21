<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/FcmPublisher.php';

$config = hardwire_load_config();
$serviceFile = (string)($config['service_account_file'] ?? '');
$serviceInfo = $serviceFile !== '' ? hardwire_service_account_public_info($serviceFile) : [];

$checks = [
    'php_7_4_or_newer' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'curl' => extension_loaded('curl') && function_exists('curl_init'),
    'openssl' => extension_loaded('openssl') && function_exists('openssl_sign'),
    'json' => extension_loaded('json'),
    'service_account_found' => $serviceFile !== '' && hardwire_is_service_account_file($serviceFile),
];

$ready = !in_array(false, $checks, true);
$result = [
    'service' => 'Hardwire Push',
    'ready' => $ready,
    'php_version' => PHP_VERSION,
    'checks' => $checks,
    'firebase' => [
        'project_id' => (string)($serviceInfo['project_id'] ?? ''),
        'client_email' => (string)($serviceInfo['client_email'] ?? ''),
        'topic' => (string)($config['topic'] ?? 'hardwire-events'),
        'credentials_detected' => $checks['service_account_found'],
    ],
    'webhook' => [
        'configured' => ((string)($config['webhook_secret'] ?? '')) !== '',
        'note' => 'Webhook secret is optional for direct PHP integration.php.',
    ],
    'direct_integration' => [
        'ready' => $ready,
        'file' => 'integration.php',
        'function' => 'hardwire_push_event_safe',
    ],
];

// Optional authenticated OAuth probe. It verifies the Service Account against
// Google without sending a notification.
if (isset($_GET['probe']) && (string)$_GET['probe'] === '1') {
    $secret = (string)($config['webhook_secret'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_HARDWIRE_TOKEN'] ?? '');
    if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
        http_response_code(401);
        $result['probe'] = ['ok' => false, 'error' => 'X-Hardwire-Token required.'];
    } elseif (!$ready) {
        http_response_code(503);
        $result['probe'] = ['ok' => false, 'error' => 'Local checks failed.'];
    } else {
        try {
            $publisher = new FcmPublisher($serviceFile, (string)$config['topic']);
            $result['probe'] = $publisher->verifyCredentials();
        } catch (Throwable $e) {
            http_response_code(503);
            $result['probe'] = ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
