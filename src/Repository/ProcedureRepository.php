<?php

namespace App\Repository;

use App\Entity\Procedure;
use App\Entity\PublicEntity;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Procedure>
 */
class ProcedureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Procedure::class);
    }

    /**
     * Récupère les procédures publiées triées par ordre d'affichage
     */
    public function findPublishedProcedures(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->leftJoin('pa.department', 'd')
            ->addSelect('f', 'pa', 'd')
            ->andWhere('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les procédures publiées pour la page d'accueil (limite à 3)
     */
    public function findHomePageProcedures(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->addSelect('f', 'pa')
            ->andWhere('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les procédures publiées avec filtres
     */
    public function findPublishedProceduresWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->leftJoin('pa.department', 'd')
            ->addSelect('f', 'pa', 'd')
            ->andWhere('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('published', true)
            ->setParameter('active', true);

        if (!empty($filters['family'])) {
            $qb->andWhere('p.family = :family')
               ->setParameter('family', $filters['family']);
        }

        if (!empty($filters['administration'])) {
            $qb->andWhere('p.providingAdministration = :administration')
               ->setParameter('administration', $filters['administration']);
        }

        if (!empty($filters['department'])) {
            $qb->andWhere('pa.department = :department')
               ->setParameter('department', $filters['department']);
        }
        
        if (!empty($filters['search'])) {
            $qb->andWhere('p.pname LIKE :search OR p.shortdesc LIKE :search OR p.longdesc LIKE :search OR pa.institutionName LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les procédures par administration
     */
    public function findByProvidingAdministration(PublicEntity $administration): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->addSelect('f')
            ->andWhere('p.providingAdministration = :administration')
            ->andWhere('p.isActive = :active')
            ->setParameter('administration', $administration)
            ->setParameter('active', true)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.pname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les procédures publiées par administration
     */
    public function findPublishedByProvidingAdministration(PublicEntity $administration): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->addSelect('f')
            ->andWhere('p.providingAdministration = :administration')
            ->andWhere('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('administration', $administration)
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.pname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les procédures par famille et administration
     */
    public function findByFamilyAndAdministration(Family $family, PublicEntity $administration = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.providingAdministration', 'pa')
            ->addSelect('pa')
            ->andWhere('p.family = :family')
            ->andWhere('p.isActive = :active')
            ->setParameter('family', $family)
            ->setParameter('active', true);

        if ($administration) {
            $qb->andWhere('p.providingAdministration = :administration')
               ->setParameter('administration', $administration);
        }

        return $qb->orderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.pname', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compte les procédures par administration
     */
    public function countByProvidingAdministration(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('pa.id as administrationId, pa.institutionName as administrationName, COUNT(p.id) as procedureCount')
            ->leftJoin('p.providingAdministration', 'pa')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('pa.id')
            ->orderBy('procedureCount', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            if ($row['administrationId']) {
                $counts[$row['administrationId']] = [
                    'name' => $row['administrationName'],
                    'count' => (int)$row['procedureCount']
                ];
            }
        }

        return $counts;
    }

    /**
     * Récupère les familles uniques des procédures publiées
     */
    public function findUniqueFamilies(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT f.id, f.fname')
            ->leftJoin('p.family', 'f')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->orderBy('f.fname', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'fname', 'id');
    }

    /**
     * Récupère les administrations uniques des procédures publiées
     */
    public function findUniqueProvidingAdministrations(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT pa.id, pa.institutionName')
            ->leftJoin('p.providingAdministration', 'pa')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->andWhere('pa.id IS NOT NULL')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->orderBy('pa.institutionName', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'institutionName', 'id');
    }

    /**
     * Récupère les procédures sans administration assignée
     */
    public function findWithoutProvidingAdministration(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->addSelect('f')
            ->andWhere('p.providingAdministration IS NULL')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.family', 'ASC')
            ->addOrderBy('p.pname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les années uniques des procédures publiées
     */
    public function findUniqueYears(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT DISTINCT YEAR(created_at) as year 
                FROM procedures 
                WHERE published = :published 
                AND is_active = :active
                ORDER BY year DESC';
        
        $result = $connection->executeQuery($sql, [
            'published' => 1,
            'active' => 1
        ])->fetchAllAssociative();
        
        return array_column($result, 'year');
    }

    /**
     * Récupère la procédure précédente
     */
    public function findPreviousProcedure(Procedure $procedure): ?Procedure
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.id < :currentId')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->setParameter('currentId', $procedure->getId())
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère la procédure suivante
     */
    public function findNextProcedure(Procedure $procedure): ?Procedure
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->setParameter('currentId', $procedure->getId())
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les procédures similaires (même famille ou même administration)
     */
    public function findSimilarProcedures(Procedure $procedure, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->addSelect('f', 'pa')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->setParameter('currentId', $procedure->getId());

        // Priorité 1: Même famille ET même administration
        if ($procedure->getFamily() && $procedure->getProvidingAdministration()) {
            $qb->andWhere('(p.family = :family AND p.providingAdministration = :administration) OR p.family = :family OR p.providingAdministration = :administration')
               ->setParameter('family', $procedure->getFamily())
               ->setParameter('administration', $procedure->getProvidingAdministration())
               ->addOrderBy('CASE WHEN p.family = :family AND p.providingAdministration = :administration THEN 1 WHEN p.family = :family THEN 2 WHEN p.providingAdministration = :administration THEN 3 ELSE 4 END', 'ASC');
        } elseif ($procedure->getFamily()) {
            $qb->andWhere('p.family = :family')
               ->setParameter('family', $procedure->getFamily());
        } elseif ($procedure->getProvidingAdministration()) {
            $qb->andWhere('p.providingAdministration = :administration')
               ->setParameter('administration', $procedure->getProvidingAdministration());
        }

        $results = $qb->addOrderBy('p.createdAt', 'DESC')
                     ->setMaxResults($limit * 2) // Get more to allow shuffling
                     ->getQuery()
                     ->getResult();

        // Shuffle and limit
        if (count($results) > $limit) {
            shuffle($results);
            return array_slice($results, 0, $limit);
        }

        return $results;
    }

    /**
     * Recherche de procédures avec texte
     */
    public function searchProcedures(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->leftJoin('pa.department', 'd')
            ->addSelect('f', 'pa', 'd')
            ->where('p.isActive = :active')
            ->andWhere('p.pname LIKE :search OR p.shortdesc LIKE :search OR p.longdesc LIKE :search OR pa.institutionName LIKE :search OR f.fname LIKE :search')
            ->setParameter('active', true)
            ->setParameter('search', '%' . $searchTerm . '%')
            ->orderBy('p.pname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des procédures
     */
    public function getProcedureStats(): array
    {
        $totalProcedures = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $publishedProcedures = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isActive = :active')
            ->andWhere('p.published = :published')
            ->setParameter('active', true)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();

        $proceduresWithAdministration = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isActive = :active')
            ->andWhere('p.providingAdministration IS NOT NULL')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int)$totalProcedures,
            'published' => (int)$publishedProcedures,
            'with_administration' => (int)$proceduresWithAdministration,
            'without_administration' => (int)$totalProcedures - (int)$proceduresWithAdministration,
            'publication_rate' => $totalProcedures > 0 ? round(($publishedProcedures / $totalProcedures) * 100, 2) : 0,
            'administration_assignment_rate' => $totalProcedures > 0 ? round(($proceduresWithAdministration / $totalProcedures) * 100, 2) : 0
        ];
    }

    /**
     * Méthode alternative pour récupérer les années en utilisant uniquement PHP
     */
    public function findUniqueYearsAlternative(): array
    {
        $procedures = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $years = [];
        foreach ($procedures as $procedure) {
            $year = $procedure['createdAt']->format('Y');
            if (!in_array($year, $years)) {
                $years[] = $year;
            }
        }

        rsort($years); // Tri décroissant
        return $years;
    }

    /**
     * Méthode utilitaire pour compter les procédures par année
     */
    public function countProceduresByYear(): array
    {
        $procedures = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->where('p.published = :published')
            ->andWhere('p.isActive = :active')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        $yearCounts = [];
        foreach ($procedures as $procedure) {
            $year = $procedure['createdAt']->format('Y');
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }

        krsort($yearCounts); // Tri par année décroissante
        return $yearCounts;
    }

    /**
     * Récupère les procédures récentes
     */
    public function findRecentProcedures(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->addSelect('f', 'pa')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}