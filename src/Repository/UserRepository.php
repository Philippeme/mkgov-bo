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
     * Find user by username or email
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
     * Find active users with filters - AMÉLIORÉ pour supporter les filtres fonctionnels
     */
    public function findActiveUsersWithFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.userRoles', 'r')
            ->addSelect('r');

        // Filtre de recherche
        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.firstName', ':search'),
                $qb->expr()->like('u.lastName', ':search'),
                $qb->expr()->like('u.email', ':search'),
                $qb->expr()->like('u.username', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Filtre par rôle
        if (!empty($filters['role'])) {
            $qb->andWhere('r.name = :role')
               ->setParameter('role', $filters['role']);
        }

        // Filtre par statut de vérification
        if (isset($filters['verified']) && $filters['verified'] !== '') {
            $qb->andWhere('u.isVerified = :verified')
               ->setParameter('verified', (bool)$filters['verified']);
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
     * Count total users with filters
     */
    public function countUsersWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)');

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('u.firstName', ':search'),
                $qb->expr()->like('u.lastName', ':search'),
                $qb->expr()->like('u.email', ':search'),
                $qb->expr()->like('u.username', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['role'])) {
            $qb->leftJoin('u.userRoles', 'r')
               ->andWhere('r.name = :role')
               ->setParameter('role', $filters['role']);
        }

        if (isset($filters['verified']) && $filters['verified'] !== '') {
            $qb->andWhere('u.isVerified = :verified')
               ->setParameter('verified', (bool)$filters['verified']);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count active users
     */
    public function countActiveUsers(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find users by role
     */
    public function findByRole(string $roleName): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.userRoles', 'r')
            ->andWhere('r.name = :roleName')
            ->andWhere('u.isActive = :active')
            ->setParameter('roleName', $roleName)
            ->setParameter('active', true)
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently registered users
     */
    public function findRecentlyRegistered(int $days = 7, int $limit = 10): array
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find users with last login activity
     */
    public function findActiveUsers(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt >= :date')
            ->andWhere('u.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find inactive users
     */
    public function findInactiveUsers(int $days = 90): array
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('u.lastLoginAt'),
                $qb->expr()->lt('u.lastLoginAt', ':date')
            ))
            ->andWhere('u.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user statistics - AMÉLIORÉ avec calcul automatique de New This Month
     */
    public function getUserStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');

        // Total users
        $totalUsers = $qb->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Active users
        $activeUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Verified users
        $verifiedUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Users registered this month - AUTOMATISÉ
        $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
        $lastDayOfMonth = new \DateTime('last day of this month 23:59:59');
        
        $newUsersThisMonth = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $firstDayOfMonth)
            ->setParameter('endDate', $lastDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'verified' => $verifiedUsers,
            'new_this_month' => $newUsersThisMonth,
        ];
    }

    /**
     * Get monthly user registration statistics
     */
    public function getMonthlyRegistrationStats(int $months = 12): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('YEAR(u.createdAt) as year, MONTH(u.createdAt) as month, COUNT(u.id) as count')
            ->andWhere('u.createdAt >= :startDate')
            ->setParameter('startDate', new \DateTime("-{$months} months"))
            ->groupBy('year, month')
            ->orderBy('year', 'DESC')
            ->addOrderBy('month', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get user statistics by role
     */
    public function getUserStatisticsByRole(): array
    {
        return $this->createQueryBuilder('u')
            ->select('r.name as role_name, r.displayName as role_display_name, COUNT(u.id) as user_count')
            ->leftJoin('u.userRoles', 'r')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('r.id')
            ->orderBy('user_count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}