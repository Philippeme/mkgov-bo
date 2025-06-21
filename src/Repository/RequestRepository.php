<?php

namespace App\Repository;

use App\Entity\Request;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Request>
 */
class RequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Request::class);
    }

    /**
     * Find requests with filters and pagination
     */
    public function findWithFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('r.person', 'per')
            ->leftJoin('p.family', 'f')
            ->addSelect('p', 'per', 'f')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('r.reference', ':search'),
                $qb->expr()->like('per.firstName', ':search'),
                $qb->expr()->like('per.lastName', ':search'),
                $qb->expr()->like('p.pname', ':search'),
                $qb->expr()->like('CONCAT(per.firstName, \' \', per.lastName)', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('r.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (!empty($filters['procedure'])) {
            $qb->andWhere('r.procedure = :procedure')
               ->setParameter('procedure', $filters['procedure']);
        }

        if (!empty($filters['family'])) {
            $qb->andWhere('p.family = :family')
               ->setParameter('family', $filters['family']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('r.submittedAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('r.submittedAt <= :dateTo')
               ->setParameter('dateTo', $filters['dateTo']);
        }

        $offset = ($page - 1) * $limit;
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('r.submittedAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find requests by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('r.person', 'per')
            ->addSelect('p', 'per')
            ->andWhere('r.status = :status')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('status', $status)
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending requests
     */
    public function findPending(): array
    {
        return $this->findByStatus('pending');
    }

    /**
     * Find processing requests
     */
    public function findProcessing(): array
    {
        return $this->findByStatus('processing');
    }

    /**
     * Find overdue requests
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('r.person', 'per')
            ->addSelect('p', 'per')
            ->andWhere('r.expectedCompletionAt < :now')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('now', new \DateTime())
            ->setParameter('activeStatuses', ['pending', 'processing'])
            ->setParameter('deleted', false)
            ->orderBy('r.expectedCompletionAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find urgent requests
     */
    public function findUrgent(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('r.person', 'per')
            ->addSelect('p', 'per')
            ->andWhere('r.priority = :priority')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('priority', 'urgent')
            ->setParameter('activeStatuses', ['pending', 'processing'])
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        $total = (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $pending = (clone $qb)->andWhere('r.status = :pending')->setParameter('pending', 'pending')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $processing = (clone $qb)->andWhere('r.status = :processing')->setParameter('processing', 'processing')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $completed = (clone $qb)->andWhere('r.status = :completed')->setParameter('completed', 'completed')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $rejected = (clone $qb)->andWhere('r.status = :rejected')->setParameter('rejected', 'rejected')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        // Count overdue
        $overdue = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.expectedCompletionAt < :now')
            ->andWhere('r.status IN (:activeStatuses)')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('now', new \DateTime())
            ->setParameter('activeStatuses', ['pending', 'processing'])
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();

        // Count by priority
        $urgent = (clone $qb)->andWhere('r.priority = :urgent')->setParameter('urgent', 'urgent')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $high = (clone $qb)->andWhere('r.priority = :high')->setParameter('high', 'high')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'pending' => (int) $pending,
            'processing' => (int) $processing,
            'completed' => (int) $completed,
            'rejected' => (int) $rejected,
            'overdue' => (int) $overdue,
            'urgent' => (int) $urgent,
            'high' => (int) $high,
        ];
    }

    /**
     * Find requests by procedure
     */
    public function findByProcedure($procedure): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.person', 'per')
            ->addSelect('per')
            ->andWhere('r.procedure = :procedure')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('procedure', $procedure)
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find requests by person
     */
    public function findByPerson($person): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('p.family', 'f')
            ->addSelect('p', 'f')
            ->andWhere('r.person = :person')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('person', $person)
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count requests by status for charts
     */
    public function getStatusCounts(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('r.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count requests by procedure family
     */
    public function getRequestsByFamily(): array
    {
        return $this->createQueryBuilder('r')
            ->select('f.fname as family_name, COUNT(r.id) as count')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('p.family', 'f')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('f.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get requests submitted in date range
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('r.person', 'per')
            ->addSelect('p', 'per')
            ->andWhere('r.submittedAt BETWEEN :startDate AND :endDate')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get average processing time by procedure
     */
    public function getAverageProcessingTime(): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.pname as procedure_name, AVG(TIMESTAMPDIFF(DAY, r.submittedAt, r.completedAt)) as avg_days')
            ->leftJoin('r.procedure', 'p')
            ->andWhere('r.status = :completed')
            ->andWhere('r.completedAt IS NOT NULL')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('completed', 'completed')
            ->setParameter('deleted', false)
            ->groupBy('p.id')
            ->orderBy('avg_days', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search requests by reference or person name
     */
    public function searchByReferenceOrPerson(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.person', 'per')
            ->leftJoin('r.procedure', 'p')
            ->addSelect('per', 'p')
            ->andWhere($this->getEntityManager()->getExpressionBuilder()->orX(
                'r.reference LIKE :query',
                'per.firstName LIKE :query',
                'per.lastName LIKE :query',
                'CONCAT(per.firstName, \' \', per.lastName) LIKE :query'
            ))
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}