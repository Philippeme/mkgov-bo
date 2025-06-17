<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    /**
     * Récupère les permissions actives triées par catégorie et ordre d'affichage
     */
    public function findActivePermissions(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les permissions groupées par catégorie
     */
    public function findPermissionsByCategory(): array
    {
        $permissions = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($permissions as $permission) {
            $category = $permission->getCategory();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }

        return $grouped;
    }

    /**
     * Récupère les permissions non système (modifiables)
     */
    public function findNonSystemPermissions(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isSystem = :system')
            ->setParameter('system', false)
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les permissions avec filtres
     */
    public function findPermissionsWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('p.name', ':search'),
                $qb->expr()->like('p.label', ':search'),
                $qb->expr()->like('p.description', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['action'])) {
            $qb->andWhere('p.action = :action')
               ->setParameter('action', $filters['action']);
        }

        if (!empty($filters['resource'])) {
            $qb->andWhere('p.resource = :resource')
               ->setParameter('resource', $filters['resource']);
        }

        if (isset($filters['active'])) {
            $qb->andWhere('p.isActive = :active')
               ->setParameter('active', $filters['active']);
        }

        if (isset($filters['system'])) {
            $qb->andWhere('p.isSystem = :system')
               ->setParameter('system', $filters['system']);
        }

        return $qb->orderBy('p.category', 'ASC')
                  ->addOrderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les catégories uniques des permissions
     */
    public function findUniqueCategories(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.category')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'category');
    }

    /**
     * Récupère les actions uniques des permissions
     */
    public function findUniqueActions(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.action')
            ->where('p.action IS NOT NULL')
            ->orderBy('p.action', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'action');
    }

    /**
     * Récupère les ressources uniques des permissions
     */
    public function findUniqueResources(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.resource')
            ->where('p.resource IS NOT NULL')
            ->orderBy('p.resource', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'resource');
    }

    /**
     * Récupère une permission par son nom
     */
    public function findByName(string $name): ?Permission
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les permissions avec leurs statistiques d'utilisation
     */
    public function findPermissionsWithStats(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'COUNT(r.id) as roleCount')
            ->leftJoin('p.roles', 'r')
            ->groupBy('p.id')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les permissions par statut
     */
    public function countPermissionsByStatus(): array
    {
        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $system = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isSystem = :system')
            ->setParameter('system', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'system' => $system,
            'custom' => $total - $system
        ];
    }

    /**
     * Compte les permissions par catégorie
     */
    public function countPermissionsByCategory(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.category', 'COUNT(p.id) as count')
            ->groupBy('p.category')
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getResult();

        $categories = [];
        foreach ($result as $row) {
            $categories[$row['category']] = $row['count'];
        }

        return $categories;
    }

    /**
     * Récupère les permissions les plus utilisées
     */
    public function findMostUsedPermissions(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->select('p', 'COUNT(r.id) as roleCount')
            ->leftJoin('p.roles', 'r')
            ->groupBy('p.id')
            ->orderBy('roleCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les permissions non utilisées
     */
    public function findUnusedPermissions(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.roles', 'r')
            ->andWhere('r.id IS NULL')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée de permissions avec pagination
     */
    public function findWithPagination(array $criteria = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('p.name', ':search'),
                $qb->expr()->like('p.label', ':search'),
                $qb->expr()->like('p.description', ':search')
            ))
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['action'])) {
            $qb->andWhere('p.action = :action')
               ->setParameter('action', $criteria['action']);
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('p.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['system'])) {
            $qb->andWhere('p.isSystem = :system')
               ->setParameter('system', $criteria['system']);
        }

        $offset = ($page - 1) * $limit;

        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('p.category', 'ASC')
                  ->addOrderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre total de permissions selon les critères
     */
    public function countWithCriteria(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('p.name', ':search'),
                $qb->expr()->like('p.label', ':search'),
                $qb->expr()->like('p.description', ':search')
            ))
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['action'])) {
            $qb->andWhere('p.action = :action')
               ->setParameter('action', $criteria['action']);
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('p.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['system'])) {
            $qb->andWhere('p.isSystem = :system')
               ->setParameter('system', $criteria['system']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Vérifie si un nom de permission existe déjà
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.name = :name')
            ->setParameter('name', $name);

        if ($excludeId) {
            $qb->andWhere('p.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}