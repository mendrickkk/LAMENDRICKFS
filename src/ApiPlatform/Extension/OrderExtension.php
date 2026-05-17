<?php

declare(strict_types=1);

namespace App\ApiPlatform\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Orders;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final class OrderExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addClientFilter($queryBuilder, $resourceClass);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $this->addClientFilter($queryBuilder, $resourceClass);
    }

    private function addClientFilter(QueryBuilder $qb, string $resourceClass): void
    {
        if ($resourceClass !== Orders::class) {
            return;
        }

        $user = $this->security->getUser();
        if ($user === null) {
            return;
        }

        // Admin and staff can see all orders
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true)) {
            return;
        }

        // Customers see only their own orders
        $rootAlias = $qb->getRootAliases()[0];
        $qb->andWhere(sprintf('%s.client = :app_order_client', $rootAlias))
           ->setParameter('app_order_client', $user);
    }
}
