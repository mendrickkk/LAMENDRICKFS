<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function logAction(string $action, ?object $targetEntity = null, ?string $customTargetData = null): void
    {
        $user = $this->security->getUser();
        
        // If no user is logged in, we can't log the action
        // This should not happen in admin panel, but we need to check
        if (!$user instanceof Users) {
            // Try to get token from security to see if user exists but isn't loaded
            $token = $this->security->getToken();
            if ($token && $token->getUser() instanceof Users) {
                $user = $token->getUser();
            } else {
                // No user available, skip logging
                return;
            }
        }

        $this->createLog($user, $action, $targetEntity, $customTargetData);
    }

    private function createLog(UserInterface $user, string $action, ?object $targetEntity = null, ?string $customTargetData = null): void
    {
        if (!$user instanceof Users) {
            return;
        }

        $log = new ActivityLog();
        $log->setUserId($user->getId());
        $log->setUsername($user->getUsername());
        $log->setRole($user->getRole()); // Assuming Users entity has getRole()
        $log->setAction($action);
        
        if ($user instanceof Users) {
            $log->setUserRelation($user);
        }

        if ($targetEntity) {
            $className = (new \ReflectionClass($targetEntity))->getShortName();
            $log->setTargetEntity($className);
            
            if (method_exists($targetEntity, 'getId')) {
                $log->setTargetId($targetEntity->getId());
            }

            if ($customTargetData) {
                $log->setTargetData($customTargetData);
            } else {
                $log->setTargetData($this->generateTargetData($targetEntity));
            }
        } elseif ($customTargetData) {
            $log->setTargetData($customTargetData);
        }

        $this->entityManager->persist($log);
        // Use a separate flush to avoid recursion issues
        try {
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // If flush fails, try to clear and retry once
            $this->entityManager->clear();
            // Don't retry to avoid infinite loops
        }
    }

    public function logLogin(UserInterface $user): void
    {
        $this->createLog($user, 'LOGIN', null, 'User logged in');
    }

    public function logLogout(UserInterface $user): void
    {
        $this->createLog($user, 'LOGOUT', null, 'User logged out');
    }

    public function logCreate(object $entity): void
    {
        $this->logAction('CREATE', $entity);
    }

    public function logUpdate(object $entity): void
    {
        $this->logAction('UPDATE', $entity);
    }

    public function logDelete(object $entity, ?string $description = null): void
    {
        // For delete, we need to pass the description because the entity might be gone or we want to capture state before delete
        $this->logAction('DELETE', $entity, $description);
    }

    private function generateTargetData(object $entity): string
    {
        $className = (new \ReflectionClass($entity))->getShortName();
        $id = method_exists($entity, 'getId') ? $entity->getId() : 'N/A';
        $name = 'N/A';

        // Try to get a name or string representation
        if (method_exists($entity, 'getName')) {
            $name = $entity->getName();
        } elseif (method_exists($entity, 'getUsername')) {
            $name = $entity->getUsername();
        } elseif (method_exists($entity, 'getTitle')) {
            $name = $entity->getTitle();
        } elseif (method_exists($entity, '__toString')) {
            $name = (string) $entity;
        }

        // Special handling for specific entities
        if ($className === 'Stock') {
            // Format: Product: {productName} (ID: {stockId})
            $productName = 'Unknown Product';
            
            if (method_exists($entity, 'getProduct')) {
                $product = $entity->getProduct();
                if ($product) {
                    // Try to get product name directly
                    try {
                        if (method_exists($product, 'getName')) {
                            $productName = $product->getName();
                        }
                    } catch (\Exception $e) {
                        // If product is not fully loaded, try to reload it by ID
                        if (method_exists($product, 'getId') && $product->getId()) {
                            try {
                                $reloadedProduct = $this->entityManager->find('App\Entity\Product', $product->getId());
                                if ($reloadedProduct && method_exists($reloadedProduct, 'getName')) {
                                    $productName = $reloadedProduct->getName();
                                }
                            } catch (\Exception $e2) {
                                // If still can't load, use product ID
                                $productName = 'Product ID: ' . $product->getId();
                            }
                        }
                    }
                }
            }
            
            return sprintf('Product: %s (ID: %s)', $productName, $id);
        }

        if ($className === 'Inventory') {
            // Format: Product: {productName} (ID: {inventoryId})
            $productName = 'Unknown Product';
            
            if (method_exists($entity, 'getProduct')) {
                $product = $entity->getProduct();
                if ($product) {
                    try {
                        if (method_exists($product, 'getName')) {
                            $productName = $product->getName();
                        }
                    } catch (\Exception $e) {
                        // If product is not fully loaded, try to reload it by ID
                        if (method_exists($product, 'getId') && $product->getId()) {
                            try {
                                $reloadedProduct = $this->entityManager->find('App\Entity\Product', $product->getId());
                                if ($reloadedProduct && method_exists($reloadedProduct, 'getName')) {
                                    $productName = $reloadedProduct->getName();
                                }
                            } catch (\Exception $e2) {
                                // If still can't load, use product ID
                                $productName = 'Product ID: ' . $product->getId();
                            }
                        }
                    }
                }
            }
            
            return sprintf('Product: %s (ID: %s)', $productName, $id);
        }

        if ($className === 'Category') {
            // Format: Category: {categoryName} (ID: {categoryId})
            return sprintf('Category: %s (ID: %s)', $name, $id);
        }

        if ($className === 'Users') {
            // Format: User: {username} (ID: {userId})
            return sprintf('User: %s (ID: %s)', $name, $id);
        }

        if ($className === 'Product') {
            // Format: Product: {productName} (ID: {productId})
            return sprintf('Product: %s (ID: %s)', $name, $id);
        }
        
        return sprintf('%s: %s (ID: %s)', $className, $name, $id);
    }
}

