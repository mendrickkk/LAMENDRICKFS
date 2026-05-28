<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Orders;
use Symfony\Component\HttpKernel\KernelInterface;

final class OrderRealtimePublisher
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    public function publishNewOrder(Orders $order): void
    {
        $event = [
            'eventId' => (int) floor(microtime(true) * 1000000),
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'total' => $order->getTotal(),
            'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'customerName' => $this->resolveCustomerIdentity($order),
        ];

        if ($event['orderId'] === null) {
            return;
        }

        $this->appendEvent($event);
    }

    /**
     * @return list<array{
     *     eventId:int,
     *     orderId:int,
     *     orderNumber:string,
     *     status:string,
     *     total:float,
     *     createdAt:string,
     *     customerName:string
     * }>
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

            if (!isset($decoded['orderId'], $decoded['orderNumber'], $decoded['status'], $decoded['total'], $decoded['createdAt'], $decoded['customerName'])) {
                continue;
            }

            $events[] = [
                'eventId' => $eventId,
                'orderId' => (int) $decoded['orderId'],
                'orderNumber' => (string) $decoded['orderNumber'],
                'status' => (string) $decoded['status'],
                'total' => (float) $decoded['total'],
                'createdAt' => (string) $decoded['createdAt'],
                'customerName' => (string) $decoded['customerName'],
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
        return $this->kernel->getProjectDir() . '/var/realtime/new-orders.ndjson';
    }

    private function resolveCustomerIdentity(Orders $order): string
    {
        $name = trim((string) $order->getCustomerName());
        if ($name !== '') {
            return $name;
        }

        $client = $order->getClient();
        if ($client === null) {
            return 'Unknown customer';
        }

        if (method_exists($client, 'getEmail')) {
            $email = trim((string) $client->getEmail());
            if ($email !== '') {
                return $email;
            }
        }

        $identifier = trim((string) $client->getUserIdentifier());

        return $identifier !== '' ? $identifier : 'Unknown customer';
    }
}
