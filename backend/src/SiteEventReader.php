<?php
declare(strict_types=1);

final class SiteEventReader
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function fetch(): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is not enabled.');
        }
        if (!class_exists('DOMDocument')) {
            throw new RuntimeException('PHP DOM/XML extension is not enabled.');
        }

        $curl = curl_init($this->url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'Hardwire-Push-Watcher/1.1',
        ]);
        $html = curl_exec($curl);
        if ($html === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('Unable to load Hardwire site: ' . $error);
        }

        $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Hardwire site returned HTTP {$httpCode}.");
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML((string)$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        $events = [];
        $rows = $xpath->query('//table//tr');
        if ($rows === false) {
            return $events;
        }

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false || $cells->length < 4) {
                continue;
            }

            $timestampNode = $cells->item(0);
            $clientNode = $cells->item(1);
            $statusNode = $cells->item(2);
            $priorityNode = $cells->item(3);

            $timestamp = $this->clean($timestampNode ? $timestampNode->textContent : '');
            $client = $this->clean($clientNode ? $clientNode->textContent : '');
            $status = $this->clean($statusNode ? $statusNode->textContent : '');
            $priority = $this->clean($priorityNode ? $priorityNode->textContent : 'INFO');
            if ($timestamp === '' || $client === '' || $status === '') {
                continue;
            }

            $eventId = hash('sha256', implode('|', [$timestamp, $client, $status, $priority]));
            $events[] = [
                'event_id' => $eventId,
                'timestamp' => $timestamp,
                'client' => $client,
                'status' => $status,
                'priority' => $priority,
            ];
        }

        return $events;
    }

    private function clean(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string)preg_replace('/\s+/u', ' ', $decoded));
    }
}
