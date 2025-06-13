<?php

namespace App\Repository;

use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class FamilyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Family::class);
    }

    /**
     * Récupère les projets publiés triés par ordre d'affichage
     */
    public function findPublishedProcedures(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.published = :published')
            ->setParameter('published', true)
            ->orderBy('f.displayOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés pour la page d'accueil (limite à 3)
     */
    public function findHomePageProjects(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.published = :published')
            ->setParameter('published', true)
            ->orderBy('f.displayOrder', 'ASC')
            ->addOrderBy('f.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés avec filtres
     */
    public function findPublishedFamiliesWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.published = :published')
            ->setParameter('published', true);

        if (!empty($filters['procedure'])) {
            $qb->andWhere('f.procedure = :procedure')
               ->setParameter('procedure', $filters['procedure']);
        }

        
        if (!empty($filters['search'])) {
            $qb->andWhere('f.fname LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('f.displayOrder', 'ASC')
                  ->addOrderBy('f.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les catégories uniques des projets publiés
     */
    public function findUniqueFamilies(): array
    {
        $result = $this->createQueryBuilder('f')
            ->select('DISTINCT f.procedure')
            ->where('f.published = :published')
            ->setParameter('published', true)
            ->orderBy('f.category', 'ASC')
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
    public function findPreviousProcedure(Family $family): ?Family
    {
        return $this->createQueryBuilder('f')
            ->where('f.published = :published')
            ->andWhere('f.id < :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $project->getId())
            ->orderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le projet suivant
     */
    public function findNextFamily(Family $family): ?Family
    {
        return $this->createQueryBuilder('f')
            ->where('f.published = :published')
            ->andWhere('f.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $family->getId())
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les projets similaires (même catégorie)
     * Remplacement de RAND() par une approche compatible avec Doctrine
     */
    public function findSimilarFamilies(Family $family, int $limit = 3): array
    {
        // Première approche : récupérer tous les projets similaires
        $allSimilarFamilies = $this->createQueryBuilder('f')
            ->where('f.published = :published')
            ->andWhere('f.category = :category')
            ->andWhere('f.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('category', $project->getCategory())
            ->setParameter('currentId', $project->getId())
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Si nous avons plus de projets que la limite demandée, mélanger aléatoirement
        if (count($allSimilarFamilies) > $limit) {
            shuffle($allSimilarFamilies);
            return array_slice($allSimilarFamilies, 0, $limit);
        }

        return $allSimilarFamilies;
    }

    /**
     * Alternative pour les projets similaires utilisant une requête SQL native avec RAND()
     * Cette méthode peut être utilisée si vous préférez l'ordre vraiment aléatoire de la base de données
     */
    public function findSimilarFamiliesWithRandomOrder(Family $family, int $limit = 3): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT f.* FROM families f 
                WHERE f.published = :published 
                AND f.procedure = :procedure 
                AND f.id != :currentId 
                ORDER BY RAND() 
                LIMIT :limit';
        
        $result = $connection->executeQuery($sql, [
            'published' => 1,
            'procedure' => $family->getFamily(),
            'currentId' => $family->getId(),
            'limit' => $limit
        ])->fetchAllAssociative();

        // Convertir les résultats en entités Project
        $families = [];
        foreach ($result as $row) {
            $familyEntity = $this->find($row['id']);
            if ($familyEntity) {
                $families[] = $familyEntity;
            }
        }

        return $families;
    }

    /**
     * Méthode alternative pour récupérer les années en utilisant uniquement PHP
     * Cette approche évite complètement les fonctions SQL
     */
    public function findUniqueYearsAlternative(): array
    {
        $projects = $this->createQueryBuilder('f')
            ->select('f.createdAt')
            ->where('f.published = :published')
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