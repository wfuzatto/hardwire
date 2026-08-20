<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = is_file(__DIR__ . '/../config.php')
    ? require __DIR__ . '/../config.php'
    : require __DIR__ . '/../config.example.php';
require __DIR__ . '/../src/FcmPublisher.php';
require __DIR__ . '/../src/SiteEventReader.php';

$siteUrl = getenv('HARDWIRE_SITE_URL') ?: 'https://prodatastelecom.com.br/sites/hardwire/';
$pollSeconds = max(1, (int)(getenv('HARDWIRE_POLL_SECONDS') ?: 2));
$stateFile = getenv('HARDWIRE_STATE_FILE') ?: __DIR__ . '/../runtime/watcher-state.json';
@mkdir(dirname($stateFile), 0770, true);

$known = [];
if (is_file($stateFile)) {
    $saved = json_decode((string)file_get_contents($stateFile), true);
    if (is_array($saved)) {
        $known = array_fill_keys(array_values(array_filter($saved, 'is_string')), true);
    }
}

$reader = new SiteEventReader($siteUrl);
$publisher = new FcmPublisher((string)$config['service_account_file'], (string)($config['topic'] ?? 'hardwire-events'));
$firstSuccessfulRead = count($known) === 0;

fwrite(STDOUT, "Hardwire watcher started: {$siteUrl}; interval={$pollSeconds}s\n");

while (true) {
    try {
        $events = $reader->fetch();

        if ($firstSuccessfulRead) {
            foreach (array_slice($events, 0, 300) as $event) {
                $known[$event['event_id']] = true;
            }
            $firstSuccessfulRead = false;
            saveState($stateFile, array_keys($known));
            fwrite(STDOUT, date('c') . " baseline loaded: " . count($known) . " events\n");
        } else {
            $newEvents = array_values(array_filter(
                $events,
                static fn(array $event): bool => !isset($known[$event['event_id']])
            ));

            foreach (array_reverse($newEvents) as $event) {
                $publisher->sendEvent($event);
                $known[$event['event_id']] = true;
                fwrite(STDOUT, date('c') . " PUSH {$event['client']} | {$event['status']}\n");
            }

            $recentIds = [];
            foreach (array_slice($events, 0, 300) as $event) {
                $recentIds[$event['event_id']] = true;
            }
            $known = $recentIds;
            saveState($stateFile, array_keys($known));
        }
    } catch (Throwable $e) {
        fwrite(STDERR, date('c') . ' ERROR ' . $e->getMessage() . PHP_EOL);
    }

    sleep($pollSeconds);
}

function saveState(string $file, array $ids): void
{
    $tmp = $file . '.tmp';
    file_put_contents($tmp, json_encode(array_slice($ids, 0, 300), JSON_PRETTY_PRINT), LOCK_EX);
    rename($tmp, $file);
}
