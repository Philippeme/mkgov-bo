<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
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
    public function findPublishedPermissionsWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.published = :published')
            ->setParameter('published', true);

        if (!empty($filters['procedure'])) {
            $qb->andWhere('p.procedure = :procedure')
               ->setParameter('procedure', $filters['procedure']);
        }

        
        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
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
    public function findUniquePermissions(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('DISTINCT p.procedure')
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
                FROM users 
                WHERE published = :published 
                ORDER BY year DESC';
        
        $result = $connection->executeQuery($sql, ['published' => 1])->fetchAllAssociative();
        
        return array_column($result, 'year');
    }

    /**
     * Récupère le projet précédent
     */
    public function findPreviousProcedure(Permission $permission): ?Permission 
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.id < :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $user->getId())
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le projet suivant
     */
    public function findNextPermission(Permission $permission): ?Permission 
    {
        return $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.id > :currentId')
            ->setParameter('published', true)
            ->setParameter('currentId', $permission->getId())
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les projets similaires (même catégorie)
     * Remplacement de RAND() par une approche compatible avec Doctrine
     */
    public function findSimilarPermissions(Permission $permission, int $limit = 3): array
    {
        // Première approche : récupérer tous les projets similaires
        $allSimilarFamilies = $this->createQueryBuilder('p')
            ->where('p.published = :published')
            ->andWhere('p.category = :category')
            ->andWhere('p.id != :currentId')
            ->setParameter('published', true)
            ->setParameter('category', $user->getCategory())
            ->setParameter('currentId', $user->getId())
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Si nous avons plus de projets que la limite demandée, mélanger aléatoirement
        if (count($allSimilarPermissions) > $limit) {
            shuffle($allSimilarPermissions);
            return array_slice($allSimilarPermissions, 0, $limit);
        }

        return $allSimilarPermissions;
    }

    /**
     * Alternative pour les projets similaires utilisant une requête SQL native avec RAND()
     * Cette méthode peut être utilisée si vous préférez l'ordre vraiment aléatoire de la base de données
     */
    public function findSimilarPermissionsWithRandomOrder(Permission $permission, int $limit = 3): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $sql = 'SELECT p.* FROM permissions p 
                WHERE p.published = :published 
                AND p.procedure = :procedure 
                AND p.id != :currentId 
                ORDER BY RAND() 
                LIMIT :limit';
        
        $result = $connection->executeQuery($sql, [
            'published' => 1,
            'procedure' => $family->getPermission(),
            'currentId' => $family->getId(),
            'limit' => $limit
        ])->fetchAllAssociative();

        // Convertir les résultats en entités Project
        $permissions = [];
        foreach ($result as $row) {
            $permissionEntity = $this->find($row['id']);
            if ($permissionEntity) {
                $permissions[] = $permissionEntity;
            }
        }

        return $permissions;
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
        foreach ($users as $user) {
            $year = $user['createdAt']->format('Y');
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
    public function countUsersByYear(): array
    {
        $users = $this->createQueryBuilder('p')
            ->select('p.createdAt')
            ->where('p.published = :published')
            ->setParameter('published', true)
            ->getQuery()
            ->getResult();

        $yearCounts = [];
        foreach ($users as $user) {
            $year = $user['createdAt']->format('Y');
            $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
        }

        krsort($yearCounts); // Tri par année décroissante
        return $yearCounts;
    }
}