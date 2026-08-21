<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/src/FcmPublisher.php';

/**
 * Direct server-side integration. Call this immediately after persisting a new
 * Hardwire event. No HTTP request or webhook secret is required when included
 * from the existing PHP application.
 */
function hardwire_push_event(
    string $client,
    string $status,
    string $priority = 'INFO',
    ?string $timestamp = null,
    ?string $eventId = null
): array {
    $client = trim($client);
    $status = trim($status);
    $priority = trim($priority) ?: 'INFO';
    $timestamp = trim((string)$timestamp) ?: date('d/m/Y H:i:s');

    if ($client === '' || $status === '') {
        throw new InvalidArgumentException('Hardwire push requires client and status.');
    }

    $eventId = trim((string)$eventId);
    if ($eventId === '') {
        $eventId = hash('sha256', implode('|', [$timestamp, $client, $status, $priority]));
    }

    $event = [
        'event_id' => $eventId,
        'timestamp' => $timestamp,
        'client' => hardwire_truncate($client, 150),
        'status' => hardwire_truncate($status, 200),
        'priority' => hardwire_truncate($priority, 40),
    ];

    $config = hardwire_load_config();
    $serviceFile = (string)($config['service_account_file'] ?? '');
    if ($serviceFile === '') {
        throw new RuntimeException(
            'Firebase Service Account not found. Put the private JSON in the push folder '
            . 'or set FIREBASE_SERVICE_ACCOUNT.'
        );
    }

    $publisher = new FcmPublisher(
        $serviceFile,
        (string)($config['topic'] ?? 'hardwire-events')
    );

    return [
        'event' => $event,
        'fcm' => $publisher->sendEvent($event),
    ];
}

/**
 * Safe variant for production monitoring: the original event processing never
 * fails because of a push outage. Errors are written to the PHP error log.
 */
function hardwire_push_event_safe(
    string $client,
    string $status,
    string $priority = 'INFO',
    ?string $timestamp = null,
    ?string $eventId = null
): bool {
    try {
        hardwire_push_event($client, $status, $priority, $timestamp, $eventId);
        return true;
    } catch (Throwable $e) {
        error_log('[Hardwire Push] ' . $e->getMessage());
        return false;
    }
}

/**
 * Adapter for rows/arrays that use either Portuguese or English field names.
 */
function hardwire_push_from_array(array $row): bool
{
    $client = (string)($row['client'] ?? $row['cliente'] ?? $row['customer'] ?? '');
    $status = (string)($row['status'] ?? $row['estado'] ?? $row['situacao'] ?? '');
    $priority = (string)($row['priority'] ?? $row['prioridade'] ?? 'INFO');
    $timestamp = $row['timestamp'] ?? $row['data_hora'] ?? $row['datahora'] ?? null;
    $eventId = $row['event_id'] ?? $row['id_evento'] ?? $row['id'] ?? null;

    return hardwire_push_event_safe(
        $client,
        $status,
        $priority,
        $timestamp !== null ? (string)$timestamp : null,
        $eventId !== null ? (string)$eventId : null
    );
}
