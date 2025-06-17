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
     * Récupère les rôles actifs triés par ordre d'affichage
     */
    public function findActiveRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('r.displayOrder', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les rôles non système (modifiables)
     */
    public function findNonSystemRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isSystem = :system')
            ->setParameter('system', false)
            ->orderBy('r.displayOrder', 'ASC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les rôles avec filtres
     */
    public function findRolesWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('r');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('r.name', ':search'),
                $qb->expr()->like('r.label', ':search'),
                $qb->expr()->like('r.description', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (isset($filters['active'])) {
            $qb->andWhere('r.isActive = :active')
               ->setParameter('active', $filters['active']);
        }

        if (isset($filters['system'])) {
            $qb->andWhere('r.isSystem = :system')
               ->setParameter('system', $filters['system']);
        }

        return $qb->orderBy('r.displayOrder', 'ASC')
                  ->addOrderBy('r.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère un rôle par son nom
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
     * Récupère les rôles avec leurs statistiques d'utilisation
     */
    public function findRolesWithStats(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r', 'COUNT(u.id) as userCount', 'COUNT(p.id) as permissionCount')
            ->leftJoin('r.users', 'u')
            ->leftJoin('r.permissions', 'p')
            ->groupBy('r.id')
            ->orderBy('r.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les rôles par statut
     */
    public function countRolesByStatus(): array
    {
        $total = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $system = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.isSystem = :system')
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
     * Récupère les rôles les plus utilisés
     */
    public function findMostUsedRoles(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('r', 'COUNT(u.id) as userCount')
            ->leftJoin('r.users', 'u')
            ->groupBy('r.id')
            ->orderBy('userCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les rôles sans utilisateurs assignés
     */
    public function findUnusedRoles(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.users', 'u')
            ->andWhere('u.id IS NULL')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les rôles avec permissions spécifiques
     */
    public function findRolesWithPermission(string $permissionName): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.permissions', 'p')
            ->andWhere('p.name = :permissionName')
            ->setParameter('permissionName', $permissionName)
            ->orderBy('r.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée de rôles avec pagination
     */
    public function findWithPagination(array $criteria = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('r');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('r.name', ':search'),
                $qb->expr()->like('r.label', ':search'),
                $qb->expr()->like('r.description', ':search')
            ))
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('r.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['system'])) {
            $qb->andWhere('r.isSystem = :system')
               ->setParameter('system', $criteria['system']);
        }

        $offset = ($page - 1) * $limit;

        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('r.displayOrder', 'ASC')
                  ->addOrderBy('r.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre total de rôles selon les critères
     */
    public function countWithCriteria(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('r.name', ':search'),
                $qb->expr()->like('r.label', ':search'),
                $qb->expr()->like('r.description', ':search')
            ))
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('r.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['system'])) {
            $qb->andWhere('r.isSystem = :system')
               ->setParameter('system', $criteria['system']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Vérifie si un nom de rôle existe déjà
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.name = :name')
            ->setParameter('name', $name);

        if ($excludeId) {
            $qb->andWhere('r.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }
}