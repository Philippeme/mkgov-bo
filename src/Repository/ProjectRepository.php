<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Récupère les projets publiés triés par ordre d'affichage
     */
    public function findPublishedProjects(): array
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
    public function findPublishedProjectsWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.published = :published')
            ->setParameter('published', true);

        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['year'])) {
            $qb->andWhere('YEAR(p.createdAt) = :year')
               ->setParameter('year', $filters['year']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('p.title LIKE :search OR p.description LIKE :search OR p.excerpt LIKE :search')
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
    public function findUniqueCategories(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.category')
            ->where('p.published = :published')
            ->setParameter('published', true)
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'category');
    }

    /**
     * Récupère les années uniques des projets publiés
     */
    public function findUniqueYears(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT YEAR(p.createdAt) as year')
            ->where('p.published = :published')
            ->setParameter('published', true)
            ->orderBy('year', 'DESC')
            ->getQuery()
            ->getResult();

        return array_column($result, 'year');
    }

    /**
     * Récupère le projet précédent
     */
    public function findPreviousProject(Project $project): ?Project
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
    public function findNextProject(Project $project): ?Project
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $project->getId())
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les projets similaires (même catégorie)
     */
    public function findSimilarProjects(Project $project, int $limit = 3): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.category = :category')
            ->andWhere('p.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('category', $project->getCategory())
            ->setParameter('currentId', $project->getId())
            ->orderBy('RAND()')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}