<?php

namespace App\Repository;

use App\Entity\Legaltext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class LegaltextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Legaltext::class);
    }

    /**
     * Récupère les projets publiés triés par ordre d'affichage
     */
    public function findPublishedInstitutions(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.published = :published')
            ->setParameter('published', true)
            ->orderBy('l.displayOrder', 'ASC')
            ->addOrderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés pour la page d'accueil (limite à 3)
     */
    public function findHomePageProjects(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.published = :published')
            ->setParameter('published', true)
            ->orderBy('l.displayOrder', 'ASC')
            ->addOrderBy('l.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés avec filtres
     */
    public function findPublishedLegaltextsWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.published = :published')
            ->setParameter('published', true);

        if (!empty($filters['institution'])) {
            $qb->andWhere('l.institution = :institution')
               ->setParameter('institution', $filters['institution']);
        }

        
        if (!empty($filters['search'])) {
            $qb->andWhere('l.title LIKE :search OR l.signature LIKE :search OR i.department LIKE :search OR i.phonenumber LIKE :search OR i.email LIKE :search OR i.contactperson LIKE :search')
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
    public function findUniqueInstitutions(): array
    {
        $result = $this->createQueryBuilder('i')
            ->select('DISTINCT i.department')
            ->where('i.published = :published')
            ->setParameter('published', true)
            ->orderBy('i.category', 'ASC')
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
    public function findPreviousInstitution(Institution $institution): ?Institution
    {
        return $this->createQueryBuilder('i')
            ->where('i.published = :published')
            ->andWhere('i.id < :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $institution->getId())
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le projet suivant
     */
    public function findNextInstitution(Institution $institution): ?Institution
    {
        return $this->createQueryBuilder('i')
            ->where('i.published = :published')
            ->andWhere('i.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $institution->getId())
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les projets similaires (même catégorie)
     * Remplacement de RAND() par une approche compatible avec Doctrine
     */
    public function findSimilarInstitutions(Institutions $institution, int $limit = 3): array
    {
        // Première approche : récupérer tous les projets similaires
        $allSimilarInstitutions = $this->createQueryBuilder('i')
            ->where('i.published = :published')
            ->andWhere('i.category = :category')
            ->andWhere('i.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('category', $project->getCategory())
            ->setParameter('currentId', $project->getId())
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Si nous avons plus de projets que la limite demandée, mélanger aléatoirement
        if (count($allSimilarInstitutions) > $limit) {
            shuffle($allSimilarInstitutions);
            return array_slice($allSimilarInstitutions, 0, $limit);
        }

        return $allSimilarInstitutions;
    }

    /**
     * Alternative pour les projets similaires utilisant une requête SQL native avec RAND()
     * Cette méthode peut être utilisée si vous préférez l'ordre vraiment aléatoire de la base de données
     */
    public function findSimilarInstitutionsWithRandomOrder(Institutions $institution, int $limit = 3): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT f.* FROM institutions i
                WHERE i.published = :published 
                AND i.department = :department  
                AND i.id != :currentId 
                ORDER BY RAND() 
                LIMIT :limit';
        
        $result = $connection->executeQuery($sql, [
            'published' => 1,
            'department' => $institution->getInstitution(),
            'currentId' => $institution->getId(),
            'limit' => $limit
        ])->fetchAllAssociative();

        // Convertir les résultats en entités Project
        $institutions = [];
        foreach ($result as $row) {
            $institutionEntity = $this->find($row['id']);
            if ($institutionEntity) {
                $institutions[] = $institutionEntity;
            }
        }

        return $institutions;
    }

    /**
     * Méthode alternative pour récupérer les années en utilisant uniquement PHP
     * Cette approche évite complètement les fonctions SQL
     */
    public function findUniqueYearsAlternative(): array
    {
        $projects = $this->createQueryBuilder('i')
            ->select('i.createdAt')
            ->where('i.published = :published')
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
        $projects = $this->createQueryBuilder('i')
            ->select('i.createdAt')
            ->where('i.published = :published')
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