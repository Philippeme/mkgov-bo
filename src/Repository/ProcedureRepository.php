<?php

namespace App\Repository;

use App\Entity\Procedure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProcedureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Procedure::class);
    }

    /**
     * Récupère les projets publiés triés par ordre d'affichage
     */
    public function findPublishedProcedures(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.published = :published')
            ->setParameter('published', true)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés pour la page d'accueil (limite à 3)
     */
    public function findHomePageProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.published = :published')
            ->setParameter('published', true)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés avec filtres
     */
    public function findPublishedProceduresWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.published = :published')
            ->setParameter('published', true);

        if (!empty($filters['family'])) {
            $qb->andWhere('p.family = :family')
               ->setParameter('family', $filters['family']);
        }

        
        if (!empty($filters['search'])) {
            $qb->andWhere('p.pname LIKE :search OR p.shortdesc LIKE :search OR p.longdesc LIKE :search OR p.excerpt LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les catégories uniques des projets publiés
     */
    public function findUniqueFamilies(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.family')
            ->where('p.published = :published')
            ->setParameter('published', true)
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'category');
    }

    /**
     * CORRECTION PRINCIPALE : Récupère les années uniques des projets publiés
     * Utilisation d'une requête SQL native pour éviter les problèmes avec la fonction YEAR()
     */
    public function findUniqueYears(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT DISTINCT YEAR(created_at) as year 
                FROM projects 
                WHERE published = :published 
                ORDER BY year DESC';
        
        $result = $connection->executeQuery($sql, ['published' => 1])->fetchAllAssociative();
        
        return array_column($result, 'year');
    }

    /**
     * Récupère le projet précédent
     */
    public function findPreviousProcedure(Procedure $procedure): ?Procedure
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.id < :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $project->getId())
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le projet suivant
     */
    public function findNextProcedure(Procedure $procedure): ?Procedure
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $procedure->getId())
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les projets similaires (même catégorie)
     * Remplacement de RAND() par une approche compatible avec Doctrine
     */
    public function findSimilarProcedures(Procedure $procedure, int $limit = 3): array
    {
        // Première approche : récupérer tous les projets similaires
        $allSimilarProcedures = $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.category = :category')
            ->andWhere('p.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('category', $project->getCategory())
            ->setParameter('currentId', $project->getId())
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Si nous avons plus de projets que la limite demandée, mélanger aléatoirement
        if (count($allSimilarProcedures) > $limit) {
            shuffle($allSimilarProcedures);
            return array_slice($allSimilarProcedures, 0, $limit);
        }

        return $allSimilarProcedures;
    }

    /**
     * Alternative pour les projets similaires utilisant une requête SQL native avec RAND()
     * Cette méthode peut être utilisée si vous préférez l'ordre vraiment aléatoire de la base de données
     */
    public function findSimilarProceduresWithRandomOrder(Procedure $procedure, int $limit = 3): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT p.* FROM procedures p 
                WHERE p.published = :published 
                AND p.family = :family 
                AND p.id != :currentId 
                ORDER BY RAND() 
                LIMIT :limit';
        
        $result = $connection->executeQuery($sql, [
            'published' => 1,
            'family' => $procedure->getFamily(),
            'currentId' => $procedure->getId(),
            'limit' => $limit
        ])->fetchAllAssociative();

        // Convertir les résultats en entités Project
        $procedures = [];
        foreach ($result as $row) {
            $procedureEntity = $this->find($row['id']);
            if ($procedureEntity) {
                $procedures[] = $procedureEntity;
            }
        }

        return $procedures;
    }

    /**
     * Méthode alternative pour récupérer les années en utilisant uniquement PHP
     * Cette approche évite complètement les fonctions SQL
     */
    public function findUniqueYearsAlternative(): array
    {
        $projects = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->where('p.published = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getResult();

        $years = [];
        foreach ($projects as $project) {
            $year = $project['createdAt']->format('Y');
            if (!in_array($year, $years)) {
                $years[] = $year;
            }
        }

        rsort($years); // Tri décroissant
        return $years;
    }

    /**
     * Méthode utilitaire pour compter les projets par année
     */
    public function countProjectsByYear(): array
    {
        $projects = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->where('p.published = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getResult();

        $yearCounts = [];
        foreach ($projects as $project) {
            $year = $project['createdAt']->format('Y');
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }

        krsort($yearCounts); // Tri par année décroissante
        return $yearCounts;
    }
}