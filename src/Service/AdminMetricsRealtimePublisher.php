<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

final class AdminMetricsRealtimePublisher
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function publishMetricsChanged(): void
    {
        $event = [
            'eventId' => (int) floor(microtime(true) * 1000000),
            'type' => 'metrics-changed',
        ];

        $this->appendEvent($event);
    }

    /**
     * @return list<array{eventId:int,type:string}>
     */
    public function readEventsAfter(int $lastEventId): array
    {
        $path = $this->eventFilePath();
        if (!is_file($path)) {
            return [];
        }

        $rows = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rows === false) {
            return [];
        }

        $events = [];
        foreach ($rows as $row) {
            $decoded = json_decode($row, true);
            if (!is_array($decoded)) {
                continue;
            }

            $eventId = (int) ($decoded['eventId'] ?? 0);
            if ($eventId <= $lastEventId) {
                continue;
            }

            $type = (string) ($decoded['type'] ?? '');
            if ($type === '') {
                continue;
            }

            $events[] = [
                'eventId' => $eventId,
                'type' => $type,
            ];
        }

        return $events;
    }

    private function appendEvent(array $event): void
    {
        $path = $this->eventFilePath();
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = @fopen($path, 'ab');
        if ($fp === false) {
            return;
        }

        try {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, json_encode($event, JSON_UNESCAPED_SLASHES) . PHP_EOL);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }

    private function eventFilePath(): string
    {
        return $this->kernel->getProjectDir() . '/var/realtime/admin-metrics.ndjson';
    }
}

