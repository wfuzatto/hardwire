<?php
declare(strict_types=1);

/**
 * Loads Hardwire push configuration with safe defaults for shared hosting.
 * A local config.php may override any value and is intentionally git-ignored.
 */
function hardwire_load_config(): array
{
    $config = [
        'webhook_secret' => getenv('HARDWIRE_WEBHOOK_SECRET') ?: '',
        'service_account_file' => getenv('FIREBASE_SERVICE_ACCOUNT') ?: '',
        'topic' => getenv('HARDWIRE_FCM_TOPIC') ?: 'hardwire-events',
    ];

    $localFile = __DIR__ . '/config.php';
    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $config = array_replace($config, $local);
        }
    }

    $config['topic'] = trim((string)($config['topic'] ?? 'hardwire-events')) ?: 'hardwire-events';
    $config['webhook_secret'] = trim((string)($config['webhook_secret'] ?? ''));
    $serviceFile = trim((string)($config['service_account_file'] ?? ''));

    if ($serviceFile !== '' && !hardwire_is_service_account_file($serviceFile)) {
        $serviceFile = '';
    }

    if ($serviceFile === '') {
        $serviceFile = hardwire_find_service_account(__DIR__);
    }

    $config['service_account_file'] = $serviceFile;
    return $config;
}

function hardwire_find_service_account(string $directory): string
{
    $patterns = [
        $directory . '/firebase-service-account.json',
        $directory . '/*firebase-adminsdk*.json',
        $directory . '/*service-account*.json',
        $directory . '/*.json',
    ];

    $seen = [];
    foreach ($patterns as $pattern) {
        $files = glob($pattern) ?: [];
        foreach ($files as $file) {
            if (isset($seen[$file])) {
                continue;
            }
            $seen[$file] = true;
            if (hardwire_is_service_account_file($file)) {
                return $file;
            }
        }
    }

    return '';
}

function hardwire_is_service_account_file(string $file): bool
{
    if ($file === '' || !is_file($file) || !is_readable($file)) {
        return false;
    }

    $decoded = json_decode((string)file_get_contents($file), true);
    return is_array($decoded)
        && !empty($decoded['project_id'])
        && !empty($decoded['private_key'])
        && !empty($decoded['client_email']);
}

function hardwire_service_account_public_info(string $file): array
{
    if (!hardwire_is_service_account_file($file)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($file), true);
    return [
        'project_id' => (string)($decoded['project_id'] ?? ''),
        'client_email' => (string)($decoded['client_email'] ?? ''),
    ];
}

function hardwire_truncate(string $value, int $limit): string
{
    if (function_exists('mb_substr')) {
        return (string)mb_substr($value, 0, $limit, 'UTF-8');
    }
    return substr($value, 0, $limit);
}
