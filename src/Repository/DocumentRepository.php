<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Procedure;
use App\Entity\Request;
use App\Entity\Person;
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
     * Find documents for a specific request
     */
    public function findByRequest(Request $request): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.procedure', 'p')
            ->leftJoin('d.person', 'per')
            ->addSelect('p', 'per')
            ->andWhere('d.request = :request')
            ->andWhere('d.isActive = :active')
            ->setParameter('request', $request)
            ->setParameter('active', true)
            ->orderBy('d.type', 'ASC')
            ->addOrderBy('d.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents for a specific person
     */
    public function findByPerson(Person $person): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.procedure', 'p')
            ->leftJoin('d.request', 'r')
            ->addSelect('p', 'r')
            ->andWhere('d.person = :person OR r.person = :person')
            ->andWhere('d.isActive = :active')
            ->setParameter('person', $person)
            ->setParameter('active', true)
            ->orderBy('d.createdAt', 'DESC')
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
     * Find output documents for a request
     */
    public function findOutputDocumentsForRequest(Request $request): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.request = :request OR (d.procedure = :procedure AND d.type = :type)')
            ->andWhere('d.isActive = :active')
            ->setParameter('request', $request)
            ->setParameter('procedure', $request->getProcedure())
            ->setParameter('type', 'output')
            ->setParameter('active', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find input documents needed for a request
     */
    public function findInputDocumentsForRequest(Request $request): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.request = :request OR (d.procedure = :procedure AND d.type = :type)')
            ->andWhere('d.isActive = :active')
            ->setParameter('request', $request)
            ->setParameter('procedure', $request->getProcedure())
            ->setParameter('type', 'input')
            ->setParameter('active', true)
            ->orderBy('d.isRequired', 'DESC')
            ->addOrderBy('d.displayOrder', 'ASC')
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
     * Count documents by status
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.status, COUNT(d.id) as count')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('d.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Find documents without associated procedure, request or person
     */
    public function findOrphanDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.procedure IS NULL')
            ->andWhere('d.request IS NULL')
            ->andWhere('d.person IS NULL')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find documents by request status
     */
    public function findByRequestStatus(string $status): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.request', 'r')
            ->addSelect('r')
            ->andWhere('r.status = :status')
            ->andWhere('d.isActive = :active')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('status', $status)
            ->setParameter('active', true)
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find expiring documents
     */
    public function findExpiringSoon(int $days = 30): array
    {
        $futureDate = new \DateTime();
        $futureDate->add(new \DateInterval('P' . $days . 'D'));

        return $this->createQueryBuilder('d')
            ->leftJoin('d.request', 'r')
            ->leftJoin('d.procedure', 'p')
            ->leftJoin('d.person', 'per')
            ->addSelect('r', 'p', 'per')
            ->andWhere('d.expirationDate BETWEEN :today AND :futureDate')
            ->andWhere('d.isActive = :active')
            ->setParameter('today', new \DateTime())
            ->setParameter('futureDate', $futureDate)
            ->setParameter('active', true)
            ->orderBy('d.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find with comprehensive filters
     */
    public function findWithFilters(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.procedure', 'p')
            ->leftJoin('d.person', 'per')
            ->leftJoin('d.request', 'r')
            ->addSelect('p', 'per', 'r')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true);

        if (!empty($filters['search'])) {
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('d.name', ':search'),
                $qb->expr()->like('d.description', ':search'),
                $qb->expr()->like('r.reference', ':search'),
                $qb->expr()->like('CONCAT(per.firstName, \' \', per.lastName)', ':search')
            ))
            ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['procedure'])) {
            $qb->andWhere('d.procedure = :procedure')
               ->setParameter('procedure', $filters['procedure']);
        }

        if (!empty($filters['request'])) {
            $qb->andWhere('d.request = :request')
               ->setParameter('request', $filters['request']);
        }

        if (!empty($filters['person'])) {
            $qb->andWhere('d.person = :person OR r.person = :person')
               ->setParameter('person', $filters['person']);
        }

        // Gestion du filtre association
        if (!empty($filters['association'])) {
            switch ($filters['association']) {
                case 'with_request':
                    $qb->andWhere('d.request IS NOT NULL');
                    break;
                case 'procedure_only':
                    $qb->andWhere('d.procedure IS NOT NULL')
                       ->andWhere('d.request IS NULL')
                       ->andWhere('d.person IS NULL');
                    break;
                case 'person_only':
                    $qb->andWhere('d.person IS NOT NULL')
                       ->andWhere('d.request IS NULL')
                       ->andWhere('d.procedure IS NULL');
                    break;
                case 'orphan':
                    $qb->andWhere('d.procedure IS NULL')
                       ->andWhere('d.request IS NULL')
                       ->andWhere('d.person IS NULL');
                    break;
            }
        }

        if (isset($filters['expiring'])) {
            if ($filters['expiring'] === 'yes') {
                $futureDate = new \DateTime();
                $futureDate->add(new \DateInterval('P30D'));
                $qb->andWhere('d.expirationDate BETWEEN :today AND :futureDate')
                   ->setParameter('today', new \DateTime())
                   ->setParameter('futureDate', $futureDate);
            } elseif ($filters['expiring'] === 'expired') {
                $qb->andWhere('d.expirationDate < :today')
                   ->setParameter('today', new \DateTime());
            }
        }

        return $qb->orderBy('d.type', 'ASC')
                  ->addOrderBy('d.displayOrder', 'ASC')
                  ->addOrderBy('d.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true);

        $total = (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        
        $byType = $this->countByType();
        $byStatus = $this->countByStatus();
        
        $expiring = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.expirationDate BETWEEN :today AND :futureDate')
            ->andWhere('d.isActive = :active')
            ->setParameter('today', new \DateTime())
            ->setParameter('futureDate', (new \DateTime())->add(new \DateInterval('P30D')))
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $withRequest = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.request IS NOT NULL')
            ->andWhere('d.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'input' => $byType['input'],
            'output' => $byType['output'],
            'expiring_soon' => (int) $expiring,
            'with_request' => (int) $withRequest,
            'by_status' => $byStatus
        ];
    }
}