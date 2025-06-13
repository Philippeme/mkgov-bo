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
                $qb->expr()->like('p.nationalId', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['city'])) {
            $qb->andWhere('p.city = :city')
               ->setParameter('city', $filters['city']);
        }

        if (!empty($filters['region'])) {
            $qb->andWhere('p.region = :region')
               ->setParameter('region', $filters['region']);
        }

        $offset = ($page - 1) * $limit;
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit)
                  ->orderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}