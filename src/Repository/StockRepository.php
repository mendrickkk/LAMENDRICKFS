<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /** Sum of stock.quantity for a product — matches admin QUANTITY column source. */
    public function getAvailableQuantityForProduct(int $productId): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.quantity), 0)')
            ->andWhere('s.product = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Stock[]
     */
    public function findByProductOrdered(int $productId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('s.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
