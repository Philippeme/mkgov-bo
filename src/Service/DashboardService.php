<?php

namespace App\Service;

use App\Repository\ProcedureRepository;
use App\Repository\FamilyRepository;
use App\Repository\RequestRepository;
use App\Repository\PublicEntityRepository;
use App\Repository\PersonRepository;
use App\Repository\UserRepository;
use App\Repository\DocumentRepository;

class DashboardService
{
    public function __construct(
        private ProcedureRepository $procedureRepository,
        private FamilyRepository $familyRepository,
        private RequestRepository $requestRepository,
        private PublicEntityRepository $publicEntityRepository,
        private PersonRepository $personRepository,
        private UserRepository $userRepository,
        private DocumentRepository $documentRepository
    ) {}

    /**
     * Get main dashboard statistics
     */
    public function getMainStatistics(): array
    {
        return [
            'institutions' => $this->publicEntityRepository->createQueryBuilder('pe')
                ->select('COUNT(pe.id)')
                ->where('pe.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'procedures' => $this->procedureRepository->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->where('p.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'families' => $this->familyRepository->createQueryBuilder('f')
                ->select('COUNT(f.id)')
                ->where('f.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'requests' => $this->requestRepository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.isDeleted = :deleted')
                ->setParameter('deleted', false)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'pending_admin' => $this->getPendingAdminRequests(),
            'pending_requestor' => $this->getPendingRequestorRequests(),
            'users' => $this->userRepository->countActiveUsers(),
            'documents' => $this->getDocumentStatistics(),
        ];
    }

    /**
     * Get requests by family for donut chart
     */
    public function getRequestsByFamily(array $filters = []): array
    {
        $qb = $this->requestRepository->createQueryBuilder('r')
            ->select('f.fname as family_name, COUNT(r.id) as count')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('p.family', 'f')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        // Apply filters
        $this->applyFilters($qb, $filters);

        $result = $qb->groupBy('f.id')
                     ->orderBy('count', 'DESC')
                     ->getQuery()
                     ->getResult();

        // Calculate percentages
        $total = array_sum(array_column($result, 'count'));
        
        return array_map(function($item) use ($total) {
            return [
                'family_name' => $item['family_name'] ?? 'Unassigned',
                'count' => (int)$item['count'],
                'percentage' => $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0
            ];
        }, $result);
    }

    /**
     * Get monthly requests data for line chart - CORRIGÉ
     */
    public function getMonthlyRequestsData(array $filters = []): array
    {
        $data = [];
        $labels = [];
        
        // Generate last 12 months from July 2024 to June 2025
        $startDate = new \DateTime('2024-07-01');
        $endDate = new \DateTime('2025-06-30');
        
        $current = clone $startDate;
        while ($current <= $endDate) {
            $monthLabel = $current->format('M Y');
            
            // Créer les dates de début et fin du mois pour la comparaison
            $monthStart = new \DateTime($current->format('Y-m-01 00:00:00'));
            $monthEnd = new \DateTime($current->format('Y-m-t 23:59:59'));
            
            $qb = $this->requestRepository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('r.submittedAt >= :monthStart')
                ->andWhere('r.submittedAt <= :monthEnd')
                ->andWhere('r.isDeleted = :deleted')
                ->setParameter('monthStart', $monthStart)
                ->setParameter('monthEnd', $monthEnd)
                ->setParameter('deleted', false);

            // Apply filters
            $this->applyFilters($qb, $filters);
            
            $count = $qb->getQuery()->getSingleScalarResult();
            
            $labels[] = $monthLabel;
            $data[] = (int)$count;
            
            $current->modify('+1 month');
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    /**
     * Get requests by gender for bar chart
     */
    public function getRequestsByGender(array $filters = []): array
    {
        $qb = $this->requestRepository->createQueryBuilder('r')
            ->join('r.person', 'p')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        // Apply filters
        $this->applyFilters($qb, $filters);

        $maleCount = (clone $qb)
            ->select('COUNT(r.id)')
            ->andWhere('p.gender = :gender')
            ->setParameter('gender', 'M')
            ->getQuery()
            ->getSingleScalarResult();

        $femaleCount = (clone $qb)
            ->select('COUNT(r.id)')
            ->andWhere('p.gender = :gender')
            ->setParameter('gender', 'F')
            ->getQuery()
            ->getSingleScalarResult();

        $total = $maleCount + $femaleCount;
        
        return [
            'labels' => ['Male', 'Female'],
            'data' => [(int)$maleCount, (int)$femaleCount],
            'percentages' => [
                'male' => $total > 0 ? round(($maleCount / $total) * 100, 1) : 0,
                'female' => $total > 0 ? round(($femaleCount / $total) * 100, 1) : 0,
            ]
        ];
    }

    /**
     * Get requests by location (Guinea-Bissau regions)
     */
    public function getRequestsByLocation(array $filters = []): array
    {
        // Guinea-Bissau regions with coordinates
        $regions = [
            'Bissau' => ['lat' => 11.8636, 'lng' => -15.5986, 'color' => '#1a4b8f'],
            'Biombo' => ['lat' => 11.8889, 'lng' => -15.7269, 'color' => '#f18221'],
            'Bolama' => ['lat' => 11.5781, 'lng' => -15.4781, 'color' => '#28a745'],
            'Cacheu' => ['lat' => 12.2750, 'lng' => -16.1667, 'color' => '#dc3545'],
            'Gabu' => ['lat' => 12.2833, 'lng' => -14.2167, 'color' => '#ffc107'],
            'Oio' => ['lat' => 12.5000, 'lng' => -15.1000, 'color' => '#17a2b8'],
            'Quinara' => ['lat' => 11.2500, 'lng' => -15.2000, 'color' => '#e83e8c'],
            'Tombali' => ['lat' => 11.1000, 'lng' => -15.0000, 'color' => '#6f42c1'],
            'Bafata' => ['lat' => 12.1667, 'lng' => -14.6667, 'color' => '#fd7e14'],
        ];

        $locationData = [];
        
        foreach ($regions as $region => $coords) {
            $qb = $this->requestRepository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->join('r.person', 'p')
                ->where('p.region = :region')
                ->andWhere('r.isDeleted = :deleted')
                ->setParameter('region', $region)
                ->setParameter('deleted', false);

            // Apply filters
            $this->applyFilters($qb, $filters);
            
            $count = $qb->getQuery()->getSingleScalarResult();

            $locationData[] = [
                'region' => $region,
                'count' => (int)$count,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'color' => $coords['color']
            ];
        }

        return $locationData;
    }

    /**
     * Get comprehensive dashboard data
     */
    public function getDashboardData(array $filters = []): array
    {
        return [
            'stats' => $this->getMainStatistics(),
            'requestsByFamily' => $this->getRequestsByFamily($filters),
            'monthlyRequests' => $this->getMonthlyRequestsData($filters),
            'requestsByGender' => $this->getRequestsByGender($filters),
            'requestsByLocation' => $this->getRequestsByLocation($filters),
            'topProcedures' => $this->getTopProcedures($filters),
            'recentActivity' => $this->getRecentActivity(),
            'performanceMetrics' => $this->getPerformanceMetrics($filters),
        ];
    }

    /**
     * Get top procedures by request count
     */
    public function getTopProcedures(array $filters = [], int $limit = 5): array
    {
        $qb = $this->requestRepository->createQueryBuilder('r')
            ->select('p.pname as procedure_name, COUNT(r.id) as count')
            ->leftJoin('r.procedure', 'p')
            ->where('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        // Apply filters
        $this->applyFilters($qb, $filters);

        return $qb->groupBy('p.id')
                  ->orderBy('count', 'DESC')
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(): array
    {
        $recentRequests = $this->requestRepository->createQueryBuilder('r')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('r.person', 'per')
            ->addSelect('p', 'per')
            ->where('r.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->orderBy('r.submittedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $recentUsers = $this->userRepository->findRecentlyRegistered(7, 5);

        return [
            'recent_requests' => $recentRequests,
            'recent_users' => $recentUsers,
        ];
    }

    /**
     * Get performance metrics - CORRIGÉ
     */
    public function getPerformanceMetrics(array $filters = []): array
    {
        $qb = $this->requestRepository->createQueryBuilder('r')
            ->where('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        // Apply filters
        $this->applyFilters($qb, $filters);

        $totalRequests = (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        
        $completedRequests = (clone $qb)
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Utilisation de TIMESTAMPDIFF compatible avec Doctrine
        $avgProcessingTime = $this->requestRepository->getEntityManager()
            ->getConnection()
            ->fetchOne('
                SELECT AVG(TIMESTAMPDIFF(DAY, submitted_at, completed_at)) 
                FROM requests 
                WHERE status = :status 
                AND completed_at IS NOT NULL 
                AND is_deleted = :deleted
            ', [
                'status' => 'completed',
                'deleted' => false
            ]);

        return [
            'completion_rate' => $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 1) : 0,
            'avg_processing_time' => $avgProcessingTime ? round($avgProcessingTime, 1) : 0,
            'total_requests' => (int)$totalRequests,
            'completed_requests' => (int)$completedRequests,
        ];
    }

    /**
     * Apply filters to query builder - CORRIGÉ
     */
    private function applyFilters($qb, array $filters): void
    {
        if (!empty($filters['region'])) {
            // Vérifier si la jointure avec person existe déjà
            $alias = $qb->getRootAliases()[0];
            $joins = $qb->getDQLPart('join');
            $hasPersonJoin = false;
            
            if (isset($joins[$alias])) {
                foreach ($joins[$alias] as $join) {
                    if (strpos($join->getJoin(), 'person') !== false) {
                        $hasPersonJoin = true;
                        break;
                    }
                }
            }
            
            if (!$hasPersonJoin) {
                $qb->join('r.person', 'person');
            }
            
            $qb->andWhere('person.region = :region')
               ->setParameter('region', $filters['region']);
        }

        if (!empty($filters['family'])) {
            // Vérifier si les jointures existent déjà
            $alias = $qb->getRootAliases()[0];
            $joins = $qb->getDQLPart('join');
            $hasProcJoin = false;
            $hasFamJoin = false;
            
            if (isset($joins[$alias])) {
                foreach ($joins[$alias] as $join) {
                    if (strpos($join->getJoin(), 'procedure') !== false) {
                        $hasProcJoin = true;
                    }
                    if (strpos($join->getJoin(), 'family') !== false) {
                        $hasFamJoin = true;
                    }
                }
            }
            
            if (!$hasProcJoin) {
                $qb->join('r.procedure', 'proc');
            }
            if (!$hasFamJoin) {
                $qb->join('proc.family', 'fam');
            }
            
            $qb->andWhere('fam.id = :family')
               ->setParameter('family', $filters['family']);
        }

        if (!empty($filters['request'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $filters['request']);
        }

        if (!empty($filters['year'])) {
            $yearStart = new \DateTime($filters['year'] . '-01-01 00:00:00');
            $yearEnd = new \DateTime($filters['year'] . '-12-31 23:59:59');
            
            $qb->andWhere('r.submittedAt >= :yearStart')
               ->andWhere('r.submittedAt <= :yearEnd')
               ->setParameter('yearStart', $yearStart)
               ->setParameter('yearEnd', $yearEnd);
        }
    }

    /**
     * Get pending admin requests
     */
    private function getPendingAdminRequests(): int
    {
        return $this->requestRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->andWhere('r.expectedCompletionAt < :now')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('status', 'processing')
            ->setParameter('now', new \DateTime())
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get pending requestor requests
     */
    private function getPendingRequestorRequests(): int
    {
        return $this->requestRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->andWhere('r.expectedCompletionAt < :now')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get document statistics
     */
    private function getDocumentStatistics(): array
    {
        return $this->documentRepository->getStatistics();
    }
}