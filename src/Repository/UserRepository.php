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
     * Find active users with filters
     */
    public function findActiveUsersWithFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true);

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
            $qb->join('u.roles', 'r')
               ->andWhere('r.name = :role')
               ->setParameter('role', $filters['role']);
        }

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
            ->join('u.roles', 'r')
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
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        $totalUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $activeUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $verifiedUsers = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        // Users registered this month
        $thisMonth = new \DateTime('first day of this month');
        $newUsersThisMonth = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :thisMonth')
            ->setParameter('thisMonth', $thisMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'verified' => $verifiedUsers,
            'new_this_month' => $newUsersThisMonth,
        ];
    }
}