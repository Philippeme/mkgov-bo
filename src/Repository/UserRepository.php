<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Récupère les utilisateurs actifs triés par ordre d'affichage
     */
    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.displayOrder', 'ASC')
            ->addOrderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les utilisateurs avec filtres
     */
    public function findUsersWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.username', ':search'),
                $qb->expr()->like('u.email', ':search'),
                $qb->expr()->like('u.firstName', ':search'),
                $qb->expr()->like('u.lastName', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['role'])) {
            $qb->andWhere('r.name = :role')
               ->setParameter('role', $filters['role']);
        }

        if (isset($filters['active'])) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', $filters['active']);
        }

        if (isset($filters['verified'])) {
            $qb->andWhere('u.isVerified = :verified')
               ->setParameter('verified', $filters['verified']);
        }

        return $qb->orderBy('u.displayOrder', 'ASC')
                  ->addOrderBy('u.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les rôles uniques des utilisateurs
     */
    public function findUniqueRoles(): array
    {
        $result = $this->createQueryBuilder('u')
            ->select('DISTINCT r.name')
            ->leftJoin('u.roles', 'r')
            ->where('r.name IS NOT NULL')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'name');
    }

    /**
     * Recherche d'utilisateurs par nom d'utilisateur ou email
     */
    public function findByUsernameOrEmail(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username = :identifier OR u.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les utilisateurs récemment actifs
     */
    public function findRecentlyActive(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify('-' . $days . ' days');

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les utilisateurs par statut
     */
    public function countUsersByStatus(): array
    {
        $total = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $active = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $verified = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        $recentlyActive = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', new \DateTime('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'verified' => $verified,
            'unverified' => $total - $verified,
            'recently_active' => $recentlyActive
        ];
    }

    /**
     * Récupère les utilisateurs avec un rôle spécifique
     */
    public function findByRole(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r')
            ->andWhere('r.name = :roleName')
            ->setParameter('roleName', $roleName)
            ->orderBy('u.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les utilisateurs sans rôle assigné
     */
    public function findUsersWithoutRoles(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r')
            ->andWhere('r.id IS NULL')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour la dernière connexion d'un utilisateur
     */
    public function updateLastLogin(User $user): void
    {
        $user->setLastLoginAt(new \DateTime());
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Recherche avancée d'utilisateurs avec pagination
     */
    public function findWithPagination(array $criteria = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.username', ':search'),
                $qb->expr()->like('u.email', ':search'),
                $qb->expr()->like('CONCAT(u.firstName, \' \', u.lastName)', ':search')
            ))
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['role'])) {
            $qb->andWhere('r.name = :role')
               ->setParameter('role', $criteria['role']);
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        $offset = ($page - 1) * $limit;

        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('u.displayOrder', 'ASC')
                  ->addOrderBy('u.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte le nombre total d'utilisateurs selon les critères
     */
    public function countWithCriteria(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->leftJoin('u.roles', 'r');

        if (!empty($criteria['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.username', ':search'),
                $qb->expr()->like('u.email', ':search'),
                $qb->expr()->like('CONCAT(u.firstName, \' \', u.lastName)', ':search')
            ))
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['role'])) {
            $qb->andWhere('r.name = :role')
               ->setParameter('role', $criteria['role']);
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }
}