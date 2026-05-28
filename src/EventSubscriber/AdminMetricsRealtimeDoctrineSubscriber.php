<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Category;
use App\Entity\Orders;
use App\Entity\Product;
use App\Entity\Stock;
use App\Entity\Users;
use App\Service\AdminMetricsRealtimePublisher;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

final class AdminMetricsRealtimeDoctrineSubscriber implements EventSubscriber
{
    private bool $shouldPublish = false;
    private bool $publishing = false;

    public function __construct(
        private readonly AdminMetricsRealtimePublisher $publisher,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions(),
        );

        foreach ($entities as $entity) {
            if (
                $entity instanceof Orders
                || $entity instanceof Stock
                || $entity instanceof Product
                || $entity instanceof Category
                || $entity instanceof Users
            ) {
                $this->shouldPublish = true;
                break;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->publishing) {
            return;
        }

        if (!$this->shouldPublish) {
            return;
        }

        $this->publishing = true;
        $this->shouldPublish = false;

        try {
            $this->publisher->publishMetricsChanged();
        } finally {
            $this->publishing = false;
        }
    }
}

