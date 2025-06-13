<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Récupère les projets publiés triés par ordre d'affichage 
     */
    public function findPublishedProcedures(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.published = :published')
            ->setParameter('published', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés pour la page d'accueil (limite à 3)
     */
    public function findHomePageProjects(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.published = :published')
            ->setParameter('published', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les projets publiés avec filtres
     */
    public function findPublishedDocumentsWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.published = :published')
            ->setParameter('published', true);

        if (!empty($filters['procedure'])) {
            $qb->andWhere('d.procedure = :procedure')
               ->setParameter('procedure', $filters['procedure']);
        }

        
        if (!empty($filters['search'])) {
            $qb->andWhere('d.dname LIKE :search OR d.description LIKE :search OR d.category LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('d.displayOrder', 'ASC')
                  ->addOrderBy('d.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Récupère les catégories uniques des projets publiés
     */
    public function findUniqueDocuments(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('DISTINCT d.procedure')
            ->where('d.published = :published')
            ->setParameter('published', true)
            ->orderBy('d.category', 'ASC')
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
    public function findPreviousProcedure(Document $document): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.published = :published')
            ->andWhere('d.id < :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $project->getId())
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le projet suivant
     */
    public function findNextDocument(Document $document): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.published = :published')
            ->andWhere('d.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $family->getId())
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les projets similaires (même catégorie)
     * Remplacement de RAND() par une approche compatible avec Doctrine
     */
    public function findSimilarDocuments(Document $document, int $limit = 3): array
    {
        // Première approche : récupérer tous les projets similaires
        $allSimilarDocuments = $this->createQueryBuilder('d')
            ->where('d.published = :published')
            ->andWhere('d.category = :category')
            ->andWhere('d.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('category', $project->getCategory())
            ->setParameter('currentId', $project->getId())
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Si nous avons plus de projets que la limite demandée, mélanger aléatoirement
        if (count($allSimilarDocuments) > $limit) {
            shuffle($allSimilarDocuments);
            return array_slice($allSimilarDocuments, 0, $limit);
        }

        return $allSimilarDocuments;
    }

    /**
     * Alternative pour les projets similaires utilisant une requête SQL native avec RAND()
     * Cette méthode peut être utilisée si vous préférez l'ordre vraiment aléatoire de la base de données
     */
    public function findSimilarDocumentsWithRandomOrder(Document $document, int $limit = 3): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT d.* FROM documents d
                WHERE d.published = :published 
                AND d.procedure = :procedure 
                AND d.id != :currentId 
                ORDER BY RAND() 
                LIMIT :limit';
        
        $result = $connection->executeQuery($sql, [
            'published' => 1,
            'procedure' => $family->getDocument(),
            'currentId' => $family->getId(),
            'limit' => $limit
        ])->fetchAllAssociative();

        // Convertir les résultats en entités Project
        $documents = [];
        foreach ($result as $row) {
            $documentEntity = $this->find($row['id']);
            if ($documentEntity) {
                $documents[] = $documentEntity;
            }
        }

        return $documents;
    }

    /**
     * Méthode alternative pour récupérer les années en utilisant uniquement PHP
     * Cette approche évite complètement les fonctions SQL
     */
    public function findUniqueYearsAlternative(): array
    {
        $projects = $this->createQueryBuilder('d')
            ->select('d.createdAt')
            ->where('d.published = :published')
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
        $projects = $this->createQueryBuilder('d')
            ->select('d.createdAt')
            ->where('d.published = :published')
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