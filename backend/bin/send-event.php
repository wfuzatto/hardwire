<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__ . '/../integration.php';

$client = $argv[1] ?? null;
$status = $argv[2] ?? null;
$priority = $argv[3] ?? 'MEDIA';
if (!$client || !$status) {
    fwrite(STDERR, "Usage: php send-event.php CLIENT STATUS [PRIORITY]\n");
    exit(2);
}

try {
    $result = hardwire_push_event((string)$client, (string)$status, (string)$priority);
    print json_encode(['ok' => true] + $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[Hardwire Push] ' . $e->getMessage() . PHP_EOL);
    exit(3);
}
