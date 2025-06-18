<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * Find active roles with filters
     */
    public function findActiveRolesWithFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true);

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('r.name', ':search'),
                $qb->expr()->like('r.displayName', ':search'),
                $qb->expr()->like('r.description', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (isset($filters['system']) && $filters['system'] !== '') {
            $qb->andWhere('r.isSystem = :system')
               ->setParameter('system', (bool)$filters['system']);
        }

        if (!empty($filters['permission'])) {
            $qb->join('r.permissions', 'p')
               ->andWhere('p.name = :permission')
               ->setParameter('permission', $filters['permission']);
        }

        $offset = ($page - 1) * $limit;
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('r.displayOrder', 'ASC')
                  ->addOrderBy('r.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find system roles
     */
    public function findSystemRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isSystem = :system')
            ->andWhere('r.isActive = :active')
            ->setParameter('system', true)
            ->setParameter('active', true)
            ->orderBy('r.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find non-system roles
     */
    public function findCustomRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isSystem = :system')
            ->andWhere('r.isActive = :active')
            ->setParameter('system', false)
            ->setParameter('active', true)
            ->orderBy('r.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find role by name
     */
    public function findByName(string $name): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find roles with specific permission
     */
    public function findRolesWithPermission(string $permissionName): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.permissions', 'p')
            ->andWhere('p.name = :permissionName')
            ->andWhere('r.isActive = :active')
            ->setParameter('permissionName', $permissionName)
            ->setParameter('active', true)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get roles statistics
     */
    public function getRoleStatistics(): array
    {
        $totalRoles = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $activeRoles = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $systemRoles = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isSystem = :system')
            ->setParameter('system', true)
            ->getQuery()
            ->getSingleScalarResult();

        $customRoles = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isSystem = :system')
            ->setParameter('system', false)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalRoles,
            'active' => $activeRoles,
            'system' => $systemRoles,
            'custom' => $customRoles,
        ];
    }

    /**
     * Get roles with user count
     */
    public function findRolesWithUserCount(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r', 'COUNT(u.id) as userCount')
            ->leftJoin('r.users', 'u')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('r.id')
            ->orderBy('r.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find most used roles
     */
    public function findMostUsedRoles(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('r', 'COUNT(u.id) as userCount')
            ->leftJoin('r.users', 'u')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('r.id')
            ->having('COUNT(u.id) > 0')
            ->orderBy('userCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unused roles
     */
    public function findUnusedRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r')
            ->leftJoin('r.users', 'u')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('r.id')
            ->having('COUNT(u.id) = 0')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}