<?php
declare(strict_types=1);

final class SiteEventReader
{
    public function __construct(private readonly string $url) {}

    public function fetch(): array
    {
        $curl = curl_init($this->url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'Hardwire-Push-Watcher/1.0',
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
        foreach ($xpath->query('//table//tr') as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false || $cells->length < 4) {
                continue;
            }
            $timestamp = $this->clean($cells->item(0)?->textContent ?? '');
            $client = $this->clean($cells->item(1)?->textContent ?? '');
            $status = $this->clean($cells->item(2)?->textContent ?? '');
            $priority = $this->clean($cells->item(3)?->textContent ?? 'INFO');
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
        return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
