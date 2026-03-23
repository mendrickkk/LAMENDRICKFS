<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Category;
use App\Entity\Inventory;
use App\Entity\Product;
use App\Entity\Stock;
use App\Entity\Users;
use App\Service\ActivityLogService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private Security $security
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->logActivity('CREATE', $args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->logActivity('UPDATE', $args);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($this->shouldLog($entity)) {
            // Ensure we have a user before logging
            $user = $this->security->getUser();
            if (!$user instanceof Users) {
                // Try to get from token
                $token = $this->security->getToken();
                if ($token) {
                    $tokenUser = $token->getUser();
                    if ($tokenUser instanceof Users) {
                        $user = $tokenUser;
                    }
                }
            }
            
            // Only log if we have a valid user
            if ($user instanceof Users) {
                // Capture data before removal
                $description = $this->generatePreRemoveDescription($entity);
                $this->activityLogService->logDelete($entity, $description);
            }
        }
    }

    private function logActivity(string $action, LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($this->shouldLog($entity)) {
            // Ensure we have a user before logging
            $user = $this->security->getUser();
            if (!$user instanceof Users) {
                // Try to get from token
                $token = $this->security->getToken();
                if ($token) {
                    $tokenUser = $token->getUser();
                    if ($tokenUser instanceof Users) {
                        $user = $tokenUser;
                    }
                }
            }
            
            // Only log if we have a valid user
            if ($user instanceof Users) {
                if ($action === 'CREATE') {
                    $this->activityLogService->logCreate($entity);
                } elseif ($action === 'UPDATE') {
                    $this->activityLogService->logUpdate($entity);
                }
            }
        }
    }

    private function shouldLog(object $entity): bool
    {
        // Don't log ActivityLog itself to prevent loops
        if ($entity instanceof ActivityLog) {
            return false;
        }

        // Only log specific entities
        return $entity instanceof Product ||
               $entity instanceof Stock ||
               $entity instanceof Category ||
               $entity instanceof Inventory ||
               $entity instanceof Users;
    }
    
    private function generatePreRemoveDescription(object $entity): string
    {
        $className = (new \ReflectionClass($entity))->getShortName();
        $id = method_exists($entity, 'getId') ? $entity->getId() : 'N/A';
        $name = 'N/A';

        if (method_exists($entity, 'getName')) {
            $name = $entity->getName();
        } elseif (method_exists($entity, 'getUsername')) {
            $name = $entity->getUsername();
        } elseif (method_exists($entity, 'getTitle')) {
            $name = $entity->getTitle();
        }

        // Custom formatting for delete messages to ensure we keep the info
        if ($entity instanceof Stock) {
             $productName = ($entity->getProduct()) ? $entity->getProduct()->getName() : 'Unknown';
             $qty = method_exists($entity, 'getQuantity') ? $entity->getQuantity() : '0';
             return sprintf('Stock: Product %s - Quantity: %s (ID: %s)', $productName, $qty, $id);
        }
        
        if ($entity instanceof Inventory) {
             $productName = ($entity->getProduct()) ? $entity->getProduct()->getName() : 'Unknown';
             $qty = method_exists($entity, 'getQuantity') ? $entity->getQuantity() : '0';
             return sprintf('Inventory: Product %s - Quantity: %s (ID: %s)', $productName, $qty, $id);
        }

        return sprintf('%s: %s (ID: %s)', $className, $name, $id);
    }
}

