<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : require __DIR__ . '/config.example.php';
require __DIR__ . '/src/FcmPublisher.php';

function fail(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Use POST.');
}

$secret = (string)($config['webhook_secret'] ?? '');
if ($secret === '' || $secret === 'CHANGE_ME') {
    fail(500, 'HARDWIRE_WEBHOOK_SECRET is not configured.');
}

$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : (string)($_SERVER['HTTP_X_HARDWIRE_TOKEN'] ?? '');
if ($token === '' || !hash_equals($secret, $token)) {
    fail(401, 'Unauthorized.');
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    fail(400, 'Invalid JSON body.');
}

$client = trim((string)($payload['client'] ?? ''));
$status = trim((string)($payload['status'] ?? ''));
$priority = trim((string)($payload['priority'] ?? 'INFO'));
$timestamp = trim((string)($payload['timestamp'] ?? date('d/m/Y H:i:s')));

if ($client === '' || $status === '') {
    fail(422, 'Fields client and status are required.');
}

$eventId = trim((string)($payload['event_id'] ?? ''));
if ($eventId === '') {
    $eventId = hash('sha256', implode('|', [$timestamp, $client, $status, $priority]));
}

$event = [
    'event_id' => $eventId,
    'timestamp' => $timestamp,
    'client' => mb_substr($client, 0, 150),
    'status' => mb_substr($status, 0, 200),
    'priority' => mb_substr($priority, 0, 40),
];

try {
    $publisher = new FcmPublisher(
        (string)$config['service_account_file'],
        (string)($config['topic'] ?? 'hardwire-events')
    );
    $fcm = $publisher->sendEvent($event);
    echo json_encode(['ok' => true, 'event' => $event, 'fcm' => $fcm], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    fail(500, $e->getMessage());
}
