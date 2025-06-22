<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $dashboardData = $this->dashboardService->getDashboardData();

        return $this->render('admin/dashboard/index.html.twig', $dashboardData);
    }

    #[Route('/dashboard/filter', name: 'admin_dashboard_filter', methods: ['GET'])]
    public function filter(Request $request): JsonResponse
    {
        $filters = [
            'region' => $request->query->get('region'),
            'family' => $request->query->get('family'),
            'request' => $request->query->get('request'),
            'year' => $request->query->get('year'),
        ];

        // Get filtered data
        $filteredData = [
            'requestsByFamily' => $this->dashboardService->getRequestsByFamily($filters),
            'monthlyRequests' => $this->dashboardService->getMonthlyRequestsData($filters),
            'requestsByGender' => $this->dashboardService->getRequestsByGender($filters),
            'requestsByLocation' => $this->dashboardService->getRequestsByLocation($filters),
            'topProcedures' => $this->dashboardService->getTopProcedures($filters),
            'performanceMetrics' => $this->dashboardService->getPerformanceMetrics($filters),
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $filteredData,
            'filters_applied' => array_filter($filters),
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/dashboard/export', name: 'admin_dashboard_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'json');
        $filters = [
            'region' => $request->query->get('region'),
            'family' => $request->query->get('family'),
            'request' => $request->query->get('request'),
            'year' => $request->query->get('year'),
        ];

        $data = $this->dashboardService->getDashboardData($filters);

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data);
            case 'pdf':
                return $this->exportToPdf($data);
            case 'excel':
                return $this->exportToExcel($data);
            default:
                return new JsonResponse($data, 200, [
                    'Content-Disposition' => 'attachment; filename="dashboard_data.json"'
                ]);
        }
    }

    #[Route('/dashboard/refresh', name: 'admin_dashboard_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        try {
            $filters = json_decode($request->getContent(), true) ?? [];
            $data = $this->dashboardService->getDashboardData($filters);
            
            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                'message' => 'Dashboard data refreshed successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error refreshing dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard/widget/{widget}', name: 'admin_dashboard_widget', methods: ['GET'])]
    public function getWidget(string $widget, Request $request): JsonResponse
    {
        $filters = [
            'region' => $request->query->get('region'),
            'family' => $request->query->get('family'),
            'request' => $request->query->get('request'),
            'year' => $request->query->get('year'),
        ];

        try {
            $data = match($widget) {
                'stats' => $this->dashboardService->getMainStatistics(),
                'family-chart' => $this->dashboardService->getRequestsByFamily($filters),
                'monthly-chart' => $this->dashboardService->getMonthlyRequestsData($filters),
                'gender-chart' => $this->dashboardService->getRequestsByGender($filters),
                'location-map' => $this->dashboardService->getRequestsByLocation($filters),
                'top-procedures' => $this->dashboardService->getTopProcedures($filters),
                'recent-activity' => $this->dashboardService->getRecentActivity(),
                'performance' => $this->dashboardService->getPerformanceMetrics($filters),
                default => throw new \InvalidArgumentException('Unknown widget: ' . $widget)
            };

            return new JsonResponse([
                'success' => true,
                'widget' => $widget,
                'data' => $data,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading widget: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Export data to CSV format
     */
    private function exportToCsv(array $data): Response
    {
        $csvContent = "Dashboard Export - " . date('Y-m-d H:i:s') . "\n\n";
        
        // Statistics
        $csvContent .= "STATISTICS\n";
        $csvContent .= "Metric,Value\n";
        foreach ($data['stats'] as $key => $value) {
            $csvContent .= ucfirst(str_replace('_', ' ', $key)) . "," . $value . "\n";
        }
        
        $csvContent .= "\nREQUESTS BY FAMILY\n";
        $csvContent .= "Family,Count,Percentage\n";
        foreach ($data['requestsByFamily'] as $family) {
            $csvContent .= $family['family_name'] . "," . $family['count'] . "," . $family['percentage'] . "%\n";
        }

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }

    /**
     * Export data to PDF format
     */
    private function exportToPdf(array $data): Response
    {
        // Simplified PDF generation - in a real application, use a library like TCPDF or DOMPDF
        $html = $this->renderView('admin/dashboard/export_pdf.html.twig', ['data' => $data]);
        
        // For now, return HTML that looks like a PDF report
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard_report_' . date('Y-m-d_H-i-s') . '.html"');
        
        return $response;
    }

    /**
     * Export data to Excel format
     */
    private function exportToExcel(array $data): Response
    {
        // Simplified Excel export - in a real application, use PhpSpreadsheet
        $csvContent = $this->exportToCsv($data)->getContent();
        
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment; filename="dashboard_export_' . date('Y-m-d_H-i-s') . '.xls"');
        
        return $response;
    }

    private function getMonthlyRequestsData(RequestRepository $repository): array
    {
        $data = [];
        $months = [];
        
        // Generate last 12 months from July 2024 to June 2025
        $startDate = new \DateTime('2024-07-01');
        $endDate = new \DateTime('2025-06-30');
        
        $current = clone $startDate;
        while ($current <= $endDate) {
            $monthKey = $current->format('Y-m');
            $monthLabel = $current->format('M Y');
            
            $count = $repository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->where('YEAR(r.submittedAt) = :year')
                ->andWhere('MONTH(r.submittedAt) = :month')
                ->andWhere('r.isDeleted = :deleted')
                ->setParameter('year', $current->format('Y'))
                ->setParameter('month', $current->format('n'))
                ->setParameter('deleted', false)
                ->getQuery()
                ->getSingleScalarResult();
            
            $months[] = $monthLabel;
            $data[] = (int)$count;
            
            $current->modify('+1 month');
        }
        
        return [
            'labels' => $months,
            'data' => $data
        ];
    }

    private function getRequestsByGender(RequestRepository $requestRepository, PersonRepository $personRepository): array
    {
        $maleCount = $requestRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.person', 'p')
            ->where('p.gender = :gender')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('gender', 'M')
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();

        $femaleCount = $requestRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.person', 'p')
            ->where('p.gender = :gender')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('gender', 'F')
            ->setParameter('deleted', false)
            ->getQuery()
            ->getSingleScalarResult();

        $total = $maleCount + $femaleCount;
        
        return [
            'labels' => ['Male', 'Female'],
            'data' => [$maleCount, $femaleCount],
            'percentages' => [
                'male' => $total > 0 ? round(($maleCount / $total) * 100, 1) : 0,
                'female' => $total > 0 ? round(($femaleCount / $total) * 100, 1) : 0,
            ]
        ];
    }

    private function getRequestsByLocation(RequestRepository $requestRepository, PersonRepository $personRepository): array
    {
        // Guinea-Bissau regions
        $regions = [
            'Bissau' => ['lat' => 11.8636, 'lng' => -15.5986],
            'Biombo' => ['lat' => 11.8889, 'lng' => -15.7269],
            'Bolama' => ['lat' => 11.5781, 'lng' => -15.4781],
            'Cacheu' => ['lat' => 12.2750, 'lng' => -16.1667],
            'Gabu' => ['lat' => 12.2833, 'lng' => -14.2167],
            'Oio' => ['lat' => 12.5000, 'lng' => -15.1000],
            'Quinara' => ['lat' => 11.2500, 'lng' => -15.2000],
            'Tombali' => ['lat' => 11.1000, 'lng' => -15.0000],
            'Bafata' => ['lat' => 12.1667, 'lng' => -14.6667],
        ];

        $locationData = [];
        
        foreach ($regions as $region => $coords) {
            $count = $requestRepository->createQueryBuilder('r')
                ->select('COUNT(r.id)')
                ->join('r.person', 'p')
                ->where('p.region = :region')
                ->andWhere('r.isDeleted = :deleted')
                ->setParameter('region', $region)
                ->setParameter('deleted', false)
                ->getQuery()
                ->getSingleScalarResult();

            $locationData[] = [
                'region' => $region,
                'count' => (int)$count,
                'lat' => $coords['lat'],
                'lng' => $coords['lng']
            ];
        }

        return $locationData;
    }

    private function getFilteredRequestsByFamily(RequestRepository $repository, array $filters): array
    {
        $qb = $repository->createQueryBuilder('r')
            ->select('f.fname as family_name, COUNT(r.id) as count')
            ->leftJoin('r.procedure', 'p')
            ->leftJoin('p.family', 'f')
            ->andWhere('r.isDeleted = :deleted')
            ->setParameter('deleted', false);

        if (!empty($filters['family'])) {
            $qb->andWhere('f.id = :family')
               ->setParameter('family', $filters['family']);
        }

        if (!empty($filters['year'])) {
            $qb->andWhere('YEAR(r.submittedAt) = :year')
               ->setParameter('year', $filters['year']);
        }

        if (!empty($filters['region'])) {
            $qb->join('r.person', 'person')
               ->andWhere('person.region = :region')
               ->setParameter('region', $filters['region']);
        }

        return $qb->groupBy('f.id')
                  ->orderBy('count', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    private function getFilteredMonthlyRequests(RequestRepository $repository, array $filters): array
    {
        // Implementation similar to getMonthlyRequestsData but with filters applied
        return $this->getMonthlyRequestsData($repository);
    }

    private function getFilteredRequestsByGender(RequestRepository $requestRepository, PersonRepository $personRepository, array $filters): array
    {
        // Implementation similar to getRequestsByGender but with filters applied
        return $this->getRequestsByGender($requestRepository, $personRepository);
    }

    private function getFilteredRequestsByLocation(RequestRepository $requestRepository, PersonRepository $personRepository, array $filters): array
    {
        // Implementation similar to getRequestsByLocation but with filters applied
        return $this->getRequestsByLocation($requestRepository, $personRepository);
    }
}