<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = is_file(__DIR__ . '/../config.php')
    ? require __DIR__ . '/../config.php'
    : require __DIR__ . '/../config.example.php';
require __DIR__ . '/../src/FcmPublisher.php';

$client = $argv[1] ?? null;
$status = $argv[2] ?? null;
$priority = $argv[3] ?? 'MEDIA';
if (!$client || !$status) {
    fwrite(STDERR, "Usage: php send-event.php CLIENT STATUS [PRIORITY]\n");
    exit(2);
}

$timestamp = date('d/m/Y H:i:s');
$event = [
    'event_id' => hash('sha256', "$timestamp|$client|$status|$priority"),
    'timestamp' => $timestamp,
    'client' => $client,
    'status' => $status,
    'priority' => $priority,
];

$publisher = new FcmPublisher((string)$config['service_account_file'], (string)($config['topic'] ?? 'hardwire-events'));
print json_encode($publisher->sendEvent($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
