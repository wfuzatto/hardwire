<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/FcmPublisher.php';
require_once __DIR__ . '/../src/SiteEventReader.php';

$config = hardwire_load_config();
$serviceFile = (string)($config['service_account_file'] ?? '');
if ($serviceFile === '') {
    fwrite(STDERR, "Firebase Service Account not found.\n");
    exit(3);
}

$siteUrl = getenv('HARDWIRE_SITE_URL') ?: 'https://prodatastelecom.com.br/sites/hardwire/';
$pollSeconds = max(1, (int)(getenv('HARDWIRE_POLL_SECONDS') ?: 2));
$stateFile = getenv('HARDWIRE_STATE_FILE') ?: __DIR__ . '/../runtime/watcher-state.json';
@mkdir(dirname($stateFile), 0770, true);

$known = [];
if (is_file($stateFile)) {
    $saved = json_decode((string)file_get_contents($stateFile), true);
    if (is_array($saved)) {
        foreach ($saved as $id) {
            if (is_string($id) && $id !== '') {
                $known[$id] = true;
            }
        }
    }
}

$reader = new SiteEventReader($siteUrl);
$publisher = new FcmPublisher($serviceFile, (string)($config['topic'] ?? 'hardwire-events'));
$firstSuccessfulRead = count($known) === 0;

fwrite(STDOUT, "Hardwire watcher started: {$siteUrl}; interval={$pollSeconds}s\n");

while (true) {
    try {
        $events = $reader->fetch();

        if ($firstSuccessfulRead) {
            foreach (array_slice($events, 0, 300) as $event) {
                $known[(string)$event['event_id']] = true;
            }
            $firstSuccessfulRead = false;
            hardwire_save_watcher_state($stateFile, array_keys($known));
            fwrite(STDOUT, date('c') . ' baseline loaded: ' . count($known) . " events\n");
        } else {
            $newEvents = [];
            foreach ($events as $event) {
                if (!isset($known[(string)$event['event_id']])) {
                    $newEvents[] = $event;
                }
            }

            foreach (array_reverse($newEvents) as $event) {
                $publisher->sendEvent($event);
                $known[(string)$event['event_id']] = true;
                fwrite(STDOUT, date('c') . " PUSH {$event['client']} | {$event['status']}\n");
            }

            $recentIds = [];
            foreach (array_slice($events, 0, 300) as $event) {
                $recentIds[(string)$event['event_id']] = true;
            }
            $known = $recentIds;
            hardwire_save_watcher_state($stateFile, array_keys($known));
        }
    } catch (Throwable $e) {
        fwrite(STDERR, date('c') . ' ERROR ' . $e->getMessage() . PHP_EOL);
    }

    sleep($pollSeconds);
}

function hardwire_save_watcher_state(string $file, array $ids): void
{
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode(array_slice($ids, 0, 300), JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $file);
}
