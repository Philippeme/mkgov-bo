<?php

namespace App\Repository;

use App\Entity\Department;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Department>
 */
class DepartmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Department::class);
    }

    /**
     * Retrieve active departments ordered by display order
     */
    public function findActiveDepartments(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieve departments with filters
     */
    public function findDepartmentsWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true);

        if (!empty($filters['search'])) {
            $qb->andWhere('d.name LIKE :search OR d.description LIKE :search OR d.code LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('d.displayOrder', 'ASC')
                  ->addOrderBy('d.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Count public entities by department
     */
    public function countPublicEntitiesByDepartment(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.id, d.name, COUNT(pe.id) as entityCount')
            ->leftJoin('d.publicEntities', 'pe')
            ->where('d.isActive = :active')
            ->andWhere('pe.isActive = :entityActive OR pe.id IS NULL')
            ->setParameter('active', true)
            ->setParameter('entityActive', true)
            ->groupBy('d.id')
            ->orderBy('d.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $departmentCounts = [];
        foreach ($result as $row) {
            $departmentCounts[$row['id']] = [
                'name' => $row['name'],
                'count' => (int)$row['entityCount']
            ];
        }

        return $departmentCounts;
    }

    /**
     * Find departments with their public entities count
     */
    public function findDepartmentsWithEntityCount(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d', 'COUNT(pe.id) as entityCount')
            ->leftJoin('d.publicEntities', 'pe', 'WITH', 'pe.isActive = :entityActive')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('entityActive', true)
            ->groupBy('d.id')
            ->orderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most active departments (with most public entities)
     */
    public function findMostActiveDepartments(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->select('d', 'COUNT(pe.id) as entityCount')
            ->leftJoin('d.publicEntities', 'pe', 'WITH', 'pe.isActive = :entityActive')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('entityActive', true)
            ->groupBy('d.id')
            ->having('COUNT(pe.id) > 0')
            ->orderBy('entityCount', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search departments by name or code
     */
    public function searchByNameOrCode(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = :active')
            ->andWhere('d.name LIKE :search OR d.code LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get unique department codes
     */
    public function findUniqueCodes(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('DISTINCT d.code')
            ->where('d.isActive = :active')
            ->andWhere('d.code IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('d.code', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'code');
    }

    /**
     * Alternative method to get years using PHP only
     */
    public function findUniqueYearsAlternative(): array
    {
        $departments = $this->createQueryBuilder('d')
            ->select('d.createdAt')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $years = [];
        foreach ($departments as $department) {
            $year = $department['createdAt']->format('Y');
            if (!in_array($year, $years)) {
                $years[] = $year;
            }
        }

        rsort($years);
        return $years;
    }

    /**
     * Utility method to count departments by year
     */
    public function countDepartmentsByYear(): array
    {
        $departments = $this->createQueryBuilder('d')
            ->select('d.createdAt')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $yearCounts = [];
        foreach ($departments as $department) {
            $year = $department['createdAt']->format('Y');
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }

        krsort($yearCounts);
        return $yearCounts;
    }
}