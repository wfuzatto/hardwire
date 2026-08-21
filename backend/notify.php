<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/integration.php';

function hardwire_fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    hardwire_fail(405, 'Use POST. Open /push/ or /push/health.php to check configuration.');
}

$config = hardwire_load_config();
$secret = (string)($config['webhook_secret'] ?? '');
if ($secret === '') {
    hardwire_fail(503, 'HTTP webhook is disabled because HARDWIRE_WEBHOOK_SECRET is not configured. Direct integration.php remains available.');
}

$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$token = '';
if (strncmp($auth, 'Bearer ', 7) === 0) {
    $token = substr($auth, 7);
}
if ($token === '') {
    $token = (string)($_SERVER['HTTP_X_HARDWIRE_TOKEN'] ?? '');
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = [];
if (strpos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        hardwire_fail(400, 'Invalid JSON body.');
    }
    $payload = $decoded;
} else {
    $payload = is_array($_POST) ? $_POST : [];
}

if ($token === '' && isset($payload['token'])) {
    $token = (string)$payload['token'];
    unset($payload['token']);
}

if ($token === '' || !hash_equals($secret, $token)) {
    hardwire_fail(401, 'Unauthorized.');
}

$client = trim((string)($payload['client'] ?? $payload['cliente'] ?? ''));
$status = trim((string)($payload['status'] ?? $payload['estado'] ?? $payload['situacao'] ?? ''));
$priority = trim((string)($payload['priority'] ?? $payload['prioridade'] ?? 'INFO'));
$timestamp = trim((string)($payload['timestamp'] ?? $payload['data_hora'] ?? $payload['datahora'] ?? ''));
$eventId = trim((string)($payload['event_id'] ?? $payload['id_evento'] ?? ''));

if ($client === '' || $status === '') {
    hardwire_fail(422, 'Fields client/cliente and status are required.');
}

try {
    $result = hardwire_push_event(
        $client,
        $status,
        $priority !== '' ? $priority : 'INFO',
        $timestamp !== '' ? $timestamp : null,
        $eventId !== '' ? $eventId : null
    );
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    hardwire_fail(500, $e->getMessage());
}
