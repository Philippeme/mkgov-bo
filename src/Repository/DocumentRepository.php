<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Procedure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Find documents by type (input or output)
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.type = :type')
            ->andWhere('d.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents for a specific procedure
     */
    public function findByProcedure(Procedure $procedure): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.procedure = :procedure')
            ->andWhere('d.isActive = :active')
            ->setParameter('procedure', $procedure)
            ->setParameter('active', true)
            ->orderBy('d.type', 'ASC')
            ->addOrderBy('d.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find required input documents for a procedure
     */
    public function findRequiredInputDocuments(Procedure $procedure): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.procedure = :procedure')
            ->andWhere('d.type = :type')
            ->andWhere('d.isRequired = :required')
            ->andWhere('d.isActive = :active')
            ->setParameter('procedure', $procedure)
            ->setParameter('type', 'input')
            ->setParameter('required', true)
            ->setParameter('active', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count documents by type
     */
    public function countByType(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.type, COUNT(d.id) as count')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('d.type')
            ->getQuery()
            ->getResult();

        $counts = ['input' => 0, 'output' => 0];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find documents without associated procedure
     */
    public function findOrphanDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.procedure IS NULL')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}