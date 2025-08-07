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
     * Trouver tous les projets avec leurs traductions
     */
    public function findAllWithTranslations(?string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->addSelect('t')
            ->setParameter('locale', $locale)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les projets actifs avec leurs traductions
     */
    public function findActiveWithTranslations(?string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->addSelect('t')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver un projet par ID avec toutes ses relations
     */
    public function findOneWithAllRelations(int $id): ?Project
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->leftJoin('p.members', 'm')
            ->leftJoin('p.links', 'l')
            ->leftJoin('p.attachments', 'a')
            ->addSelect('t', 'm', 'l', 'a')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Rechercher des projets par terme
     */
    public function searchProjects(string $searchTerm, ?string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->addSelect('t')
            ->where('p.code LIKE :search')
            ->orWhere('t.name LIKE :search')
            ->orWhere('t.description LIKE :search')
            ->orWhere('p.responsible LIKE :search')
            ->orWhere('p.department LIKE :search')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->setParameter('locale', $locale)
            ->orderBy('p.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtrer les projets par critères
     */
    public function filterProjects(array $criteria = [], ?string $locale = 'fr'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->addSelect('t')
            ->setParameter('locale', $locale);

        if (!empty($criteria['status'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['priority'])) {
            $qb->andWhere('p.priority = :priority')
               ->setParameter('priority', $criteria['priority']);
        }

        if (!empty($criteria['responsible'])) {
            $qb->andWhere('p.responsible LIKE :responsible')
               ->setParameter('responsible', '%' . $criteria['responsible'] . '%');
        }

        if (!empty($criteria['department'])) {
            $qb->andWhere('p.department = :department')
               ->setParameter('department', $criteria['department']);
        }

        if (!empty($criteria['dateStart'])) {
            $qb->andWhere('p.startDate >= :dateStart')
               ->setParameter('dateStart', new \DateTime($criteria['dateStart']));
        }

        if (!empty($criteria['dateEnd'])) {
            $qb->andWhere('p.endDate <= :dateEnd')
               ->setParameter('dateEnd', new \DateTime($criteria['dateEnd']));
        }

        if (isset($criteria['active'])) {
            $qb->andWhere('p.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        return $qb->orderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Compter les projets par statut
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        $statusCounts = [
            'planning' => 0,
            'in_progress' => 0,
            'on_hold' => 0,
            'completed' => 0,
            'cancelled' => 0
        ];

        foreach ($result as $row) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        return $statusCounts;
    }

    /**
     * Compter les projets par catégorie
     */
    public function countByCategory(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.category, COUNT(p.id) as count')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.category')
            ->getQuery()
            ->getResult();

        $categoryCounts = [
            'development' => 0,
            'research' => 0,
            'infrastructure' => 0,
            'marketing' => 0,
            'other' => 0
        ];

        foreach ($result as $row) {
            $categoryCounts[$row['category']] = (int) $row['count'];
        }

        return $categoryCounts;
    }

    /**
     * Trouver les projets récents
     */
    public function findRecentProjects(int $limit = 10, ?string $locale = 'fr'): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->addSelect('t')
            ->where('p.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('locale', $locale)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifier l'unicité du code projet
     */
    public function isCodeUnique(string $code, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.code = :code')
            ->setParameter('code', $code);

        if ($excludeId) {
            $qb->andWhere('p.id != :id')
               ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Calculer les statistiques globales
     */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('p');
        
        return [
            'total' => (int) $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(),
            'active' => (int) $qb->select('COUNT(p.id)')->where('p.isActive = true')->getQuery()->getSingleScalarResult(),
            'in_progress' => (int) $qb->select('COUNT(p.id)')->where('p.status = :status')->setParameter('status', 'in_progress')->getQuery()->getSingleScalarResult(),
            'completed' => (int) $qb->select('COUNT(p.id)')->where('p.status = :status')->setParameter('status', 'completed')->getQuery()->getSingleScalarResult(),
        ];
    }

    /**
     * Supprimer les projets sélectionnés (soft delete)
     */
    public function softDeleteByIds(array $ids): int
    {
        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.isActive', ':inactive')
            ->set('p.updatedAt', ':now')
            ->where('p.id IN (:ids)')
            ->setParameter('inactive', false)
            ->setParameter('now', new \DateTime())
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * Exporter les projets pour Excel/PDF
     */
    public function findForExport(array $ids = [], ?string $locale = 'fr'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't', 'WITH', 't.locale = :locale')
            ->addSelect('t')
            ->setParameter('locale', $locale);

        if (!empty($ids)) {
            $qb->where('p.id IN (:ids)')
               ->setParameter('ids', $ids);
        }

        return $qb->orderBy('p.displayOrder', 'ASC')
                  ->addOrderBy('p.createdAt', 'DESC')
                  ->getQuery()
                  ->getResult();
    }
}