<?php

namespace App\Repository;

use App\Entity\PublicEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicEntity>
 */
class PublicEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicEntity::class);
    }

    /**
     * Retrieve active public entities ordered by display order
     */
    public function findActivePublicEntities(): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->andWhere('pe.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pe.displayOrder', 'ASC')
            ->addOrderBy('pe.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieve public entities with filters
     */
    public function findPublicEntitiesWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->andWhere('pe.isActive = :active')
            ->setParameter('active', true);

        if (!empty($filters['department'])) {
            $qb->andWhere('pe.department = :department')
               ->setParameter('department', $filters['department']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('pe.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('pe.institutionName LIKE :search OR pe.description LIKE :search OR pe.code LIKE :search OR pe.email LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('pe.displayOrder', 'ASC')
                  ->addOrderBy('pe.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find public entities by department
     */
    public function findByDepartment($department): array
    {
        return $this->createQueryBuilder('pe')
            ->andWhere('pe.department = :department')
            ->andWhere('pe.isActive = :active')
            ->setParameter('department', $department)
            ->setParameter('active', true)
            ->orderBy('pe.displayOrder', 'ASC')
            ->addOrderBy('pe.institutionName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count entities by department
     */
    public function countByDepartment(): array
    {
        $result = $this->createQueryBuilder('pe')
            ->select('d.id as departmentId, d.name as departmentName, COUNT(pe.id) as entityCount')
            ->leftJoin('pe.department', 'd')
            ->where('pe.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('d.id')
            ->orderBy('entityCount', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['departmentId']] = [
                'name' => $row['departmentName'],
                'count' => (int)$row['entityCount']
            ];
        }

        return $counts;
    }

    /**
     * Find entities by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->andWhere('pe.status = :status')
            ->andWhere('pe.isActive = :active')
            ->setParameter('status', $status)
            ->setParameter('active', true)
            ->orderBy('pe.institutionName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search public entities
     */
    public function searchEntities(string $searchTerm): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->where('pe.isActive = :active')
            ->andWhere('pe.institutionName LIKE :search OR pe.code LIKE :search OR pe.email LIKE :search OR d.name LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('pe.institutionName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get unique statuses
     */
    public function findUniqueStatuses(): array
    {
        $result = $this->createQueryBuilder('pe')
            ->select('DISTINCT pe.status')
            ->where('pe.isActive = :active')
            ->andWhere('pe.status IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('pe.status', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'status');
    }

    /**
     * Find recently created entities
     */
    public function findRecentEntities(int $limit = 10): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->andWhere('pe.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pe.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entities with complete contact information
     */
    public function findEntitiesWithCompleteContact(): array
    {
        return $this->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->andWhere('pe.isActive = :active')
            ->andWhere('pe.email IS NOT NULL')
            ->andWhere('pe.phoneNumber IS NOT NULL')
            ->andWhere('pe.contactPersonName IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('pe.institutionName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count entities by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('pe')
            ->select('pe.status, COUNT(pe.id) as count')
            ->where('pe.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('pe.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $statusCounts = [];
        foreach ($result as $row) {
            $statusCounts[$row['status']] = (int)$row['count'];
        }

        return $statusCounts;
    }

    /**
     * Alternative method to get years using PHP only
     */
    public function findUniqueYearsAlternative(): array
    {
        $entities = $this->createQueryBuilder('pe')
            ->select('pe.createdAt')
            ->where('pe.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $years = [];
        foreach ($entities as $entity) {
            $year = $entity['createdAt']->format('Y');
            if (!in_array($year, $years)) {
                $years[] = $year;
            }
        }

        rsort($years);
        return $years;
    }

    /**
     * Utility method to count entities by year
     */
    public function countEntitiesByYear(): array
    {
        $entities = $this->createQueryBuilder('pe')
            ->select('pe.createdAt')
            ->where('pe.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $yearCounts = [];
        foreach ($entities as $entity) {
            $year = $entity['createdAt']->format('Y');
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }

        krsort($yearCounts);
        return $yearCounts;
    }
}