<?php

namespace App\Repository;

use App\Entity\Person;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Person>
 */
class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    /**
     * Find persons with filters and pagination
     */
    public function findWithFilters(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('p.firstName', ':search'),
                $qb->expr()->like('p.lastName', ':search'),
                $qb->expr()->like('p.email', ':search'),
                $qb->expr()->like('p.nationalId', ':search'),
                $qb->expr()->like('CONCAT(p.firstName, \' \', p.lastName)', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['region'])) {
            $qb->andWhere('p.region = :region')
               ->setParameter('region', $filters['region']);
        }

        if (!empty($filters['gender'])) {
            $qb->andWhere('p.gender = :gender')
               ->setParameter('gender', $filters['gender']);
        }

        if (!empty($filters['city'])) {
            $qb->andWhere('p.city = :city')
               ->setParameter('city', $filters['city']);
        }

        if (!empty($filters['maritalStatus'])) {
            $qb->andWhere('p.maritalStatus = :maritalStatus')
               ->setParameter('maritalStatus', $filters['maritalStatus']);
        }

        if (!empty($filters['verified'])) {
            if ($filters['verified'] === 'yes') {
                $qb->andWhere('p.verifiedAt IS NOT NULL');
            } elseif ($filters['verified'] === 'no') {
                $qb->andWhere('p.verifiedAt IS NULL');
            }
        }

        $offset = ($page - 1) * $limit;
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('p.lastName', 'ASC')
                  ->addOrderBy('p.firstName', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Search persons by name or national ID for autocomplete
     */
    public function searchByNameOrId(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isDeleted = :deleted')
            ->andWhere($this->getEntityManager()->getExpressionBuilder()->orX(
                'p.firstName LIKE :query',
                'p.lastName LIKE :query',
                'p.nationalId LIKE :query',
                'CONCAT(p.firstName, \' \', p.lastName) LIKE :query'
            ))
            ->setParameter('deleted', false)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find persons by region
     */
    public function findByRegion(string $region): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.region = :region')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('region', $region)
            ->setParameter('deleted', false)
            ->orderBy('p.lastName', 'ASC')
            ->addOrderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unverified persons
     */
    public function findUnverified(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.verifiedAt IS NULL')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find persons with expired documents
     */
    public function findWithExpiredDocuments(): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.documents', 'd')
            ->andWhere('p.isDeleted = :deleted')
            ->andWhere('d.isActive = :active')
            ->andWhere('d.expirationDate < :today')
            ->setParameter('deleted', false)
            ->setParameter('active', true)
            ->setParameter('today', new \DateTime())
            ->orderBy('d.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find persons with documents expiring soon
     */
    public function findWithDocumentsExpiringSoon(int $days = 30): array
    {
        $futureDate = new \DateTime();
        $futureDate->add(new \DateInterval('P' . $days . 'D'));

        return $this->createQueryBuilder('p')
            ->innerJoin('p.documents', 'd')
            ->andWhere('p.isDeleted = :deleted')
            ->andWhere('d.isActive = :active')
            ->andWhere('d.expirationDate BETWEEN :today AND :futureDate')
            ->setParameter('deleted', false)
            ->setParameter('active', true)
            ->setParameter('today', new \DateTime())
            ->setParameter('futureDate', $futureDate)
            ->orderBy('d.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count persons by various criteria
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false);

        $total = (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $verified = (clone $qb)->andWhere('p.verifiedAt IS NOT NULL')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $unverified = (clone $qb)->andWhere('p.verifiedAt IS NULL')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Count by gender
        $males = (clone $qb)->andWhere('p.gender = :male')->setParameter('male', 'M')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $females = (clone $qb)->andWhere('p.gender = :female')->setParameter('female', 'F')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Count by regions
        $regionStats = $this->createQueryBuilder('p')
            ->select('p.region, COUNT(p.id) as count')
            ->andWhere('p.isDeleted = :deleted')
            ->andWhere('p.region IS NOT NULL')
            ->setParameter('deleted', false)
            ->groupBy('p.region')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'total' => (int) $total,
            'verified' => (int) $verified,
            'unverified' => (int) $unverified,
            'males' => (int) $males,
            'females' => (int) $females,
            'regions' => $regionStats
        ];
    }

    /**
     * Find persons by age range
     */
    public function findByAgeRange(int $minAge, int $maxAge): array
    {
        $maxDate = new \DateTime();
        $maxDate->sub(new \DateInterval('P' . $minAge . 'Y'));
        
        $minDate = new \DateTime();
        $minDate->sub(new \DateInterval('P' . ($maxAge + 1) . 'Y'));

        return $this->createQueryBuilder('p')
            ->andWhere('p.dateOfBirth BETWEEN :minDate AND :maxDate')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('minDate', $minDate)
            ->setParameter('maxDate', $maxDate)
            ->setParameter('deleted', false)
            ->orderBy('p.dateOfBirth', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find persons registered in date range
     */
    public function findByRegistrationDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('deleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find duplicate national IDs (for data validation)
     */
    public function findDuplicateNationalIds(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.nationalId, COUNT(p.id) as count')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->groupBy('p.nationalId')
            ->having('count > 1')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find persons without documents
     */
    public function findWithoutDocuments(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.documents', 'd')
            ->andWhere('p.isDeleted = :deleted')
            ->andWhere('d.id IS NULL')
            ->setParameter('deleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recently registered persons
     */
    public function findRecentlyRegistered(int $days = 7): array
    {
        $date = new \DateTime();
        $date->sub(new \DateInterval('P' . $days . 'D'));

        return $this->createQueryBuilder('p')
            ->andWhere('p.createdAt >= :date')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('date', $date)
            ->setParameter('deleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}