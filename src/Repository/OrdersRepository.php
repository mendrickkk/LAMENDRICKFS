<?php

namespace App\Repository;

use App\Entity\Orders;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Orders>
 */
class OrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orders::class);
    }

    /**
     * @return Orders[]
     */
    public function findRecentForAdmin(int $limit = 50): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.lines', 'ol')->addSelect('ol')
            ->leftJoin('ol.product', 'olp')->addSelect('olp')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneWithDetails(int $id): ?Orders
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.lines', 'ol')->addSelect('ol')
            ->leftJoin('ol.product', 'olp')->addSelect('olp')
            ->leftJoin('o.products', 'op')->addSelect('op')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->andWhere('o.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Orders[]
     */
    public function findByClientOrdered(Users $client, int $limit = 50): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.products', 'op')->addSelect('op')
            ->leftJoin('o.lines', 'ol')->addSelect('ol')
            ->leftJoin('ol.product', 'olp')->addSelect('olp')
            ->andWhere('o.client = :client')
            ->setParameter('client', $client)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Orders[] Returns an array of Orders objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Orders
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
