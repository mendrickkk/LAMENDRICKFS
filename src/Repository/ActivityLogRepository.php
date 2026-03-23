<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 *
 * @method ActivityLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActivityLog[]    findAll()
 * @method ActivityLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Get recent logs ordered by createdAt DESC
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Filter logs by various criteria with pagination and sorting
     */
    public function findByFilters(array $filters, int $limit = 50, int $offset = 0, string $sort = 'createdAt', string $order = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('a');

        if (!empty($filters['user'])) {
            $qb->andWhere('a.userId = :user OR a.username LIKE :username')
               ->setParameter('user', $filters['user'])
               ->setParameter('username', '%' . $filters['user'] . '%');
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['targetEntity'])) {
            $qb->andWhere('a.targetEntity = :targetEntity')
               ->setParameter('targetEntity', $filters['targetEntity']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            // Add 1 day to include the end date fully
            $dateTo = clone $filters['dateTo'];
            $dateTo->modify('+1 day');
            $qb->andWhere('a.createdAt < :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return $qb->orderBy('a.' . $sort, $order)
                  ->setMaxResults($limit)
                  ->setFirstResult($offset)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Count logs by filters (for pagination)
     */
    public function countByFilters(array $filters): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('count(a.id)');

        if (!empty($filters['user'])) {
            $qb->andWhere('a.userId = :user OR a.username LIKE :username')
               ->setParameter('user', $filters['user'])
               ->setParameter('username', '%' . $filters['user'] . '%');
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['targetEntity'])) {
            $qb->andWhere('a.targetEntity = :targetEntity')
               ->setParameter('targetEntity', $filters['targetEntity']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $dateTo = clone $filters['dateTo'];
            $dateTo->modify('+1 day');
            $qb->andWhere('a.createdAt < :dateTo')
               ->setParameter('dateTo', $dateTo);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countTotalLogs(): int
    {
        return $this->count([]);
    }

    public function countLogsToday(): int
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        return $this->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('a.createdAt >= :today')
            ->andWhere('a.createdAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLogsThisWeek(): int
    {
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = new \DateTime('monday next week');

        return $this->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('a.createdAt >= :start')
            ->andWhere('a.createdAt < :end')
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countLogsByAction(string $action): int
    {
        return $this->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('a.action = :action')
            ->setParameter('action', $action)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

