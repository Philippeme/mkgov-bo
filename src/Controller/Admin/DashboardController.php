<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

        // CORRECTION: Seule la carte est filtrée, pas les diagrammes
        $filteredData = [
            'requestsByLocation' => $this->dashboardService->getRequestsByLocation($filters), // SEULE la carte est filtrée
            'stats' => $this->dashboardService->getMainStatistics(), // Stats non filtrées
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $filteredData, // SEULEMENT carte + stats, pas les diagrammes
            'filters_applied' => array_filter($filters),
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    #[Route('/dashboard/map-data', name: 'admin_dashboard_map_data', methods: ['GET'])]
    public function getMapData(Request $request): JsonResponse
    {
        $filters = [
            'region' => $request->query->get('region'),
            'family' => $request->query->get('family'),
            'request' => $request->query->get('request'),
            'year' => $request->query->get('year'),
        ];

        try {
            $mapData = $this->dashboardService->getFilteredMapData($filters);
            
            return new JsonResponse([
                'success' => true,
                'data' => $mapData,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error loading map data: ' . $e->getMessage()
            ], 400);
        }
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

        try {
            $data = $this->dashboardService->getDashboardData($filters);

            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($data);
                case 'pdf':
                    return $this->exportToPdf($data, $request);
                case 'excel':
                    return $this->exportToExcel($data);
                default:
                    return new JsonResponse($data, 200, [
                        'Content-Disposition' => 'attachment; filename="dashboard_data_' . date('Y-m-d_H-i-s') . '.json"'
                    ]);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
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
     * Export data to CSV format - CORRIGÉ
     */
    private function exportToCsv(array $data): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function() use ($data) {
            $handle = fopen('php://output', 'w+');
            
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            
            // Header
            fputcsv($handle, ['Dashboard Export - ' . date('Y-m-d H:i:s')]);
            fputcsv($handle, []);
            
            // Statistics
            fputcsv($handle, ['STATISTICS']);
            fputcsv($handle, ['Metric', 'Value']);
            foreach ($data['stats'] as $key => $value) {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', $key)), $value]);
            }
            
            fputcsv($handle, []);
            fputcsv($handle, ['REQUESTS BY FAMILY']);
            fputcsv($handle, ['Family', 'Count', 'Percentage']);
            foreach ($data['requestsByFamily'] as $family) {
                fputcsv($handle, [
                    $family['family_name'] ?? 'Unassigned',
                    $family['count'],
                    $family['percentage'] . '%'
                ]);
            }
            
            fputcsv($handle, []);
            fputcsv($handle, ['MONTHLY REQUESTS']);
            fputcsv($handle, ['Month', 'Count']);
            if (isset($data['monthlyRequests']['labels']) && isset($data['monthlyRequests']['data'])) {
                foreach ($data['monthlyRequests']['labels'] as $index => $month) {
                    fputcsv($handle, [$month, $data['monthlyRequests']['data'][$index] ?? 0]);
                }
            }
            
            fputcsv($handle, []);
            fputcsv($handle, ['REQUESTS BY GENDER']);
            fputcsv($handle, ['Gender', 'Count', 'Percentage']);
            if (isset($data['requestsByGender']['labels']) && isset($data['requestsByGender']['data'])) {
                foreach ($data['requestsByGender']['labels'] as $index => $gender) {
                    $percentage = $gender === 'Male' ? 
                        ($data['requestsByGender']['percentages']['male'] ?? 0) : 
                        ($data['requestsByGender']['percentages']['female'] ?? 0);
                    fputcsv($handle, [
                        $gender,
                        $data['requestsByGender']['data'][$index] ?? 0,
                        $percentage . '%'
                    ]);
                }
            }
            
            fputcsv($handle, []);
            fputcsv($handle, ['REQUESTS BY LOCATION']);
            fputcsv($handle, ['Region', 'Count']);
            foreach ($data['requestsByLocation'] as $location) {
                fputcsv($handle, [$location['region'], $location['count']]);
            }
            
            fclose($handle);
        });
        
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="mkgov_dashboard_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }

    /**
     * Export data to PDF format - CORRIGÉ
     */
    private function exportToPdf(array $data, Request $request = null): Response
    {
        // Generate HTML content for PDF
        $html = $this->renderView('admin/dashboard/export_pdf.html.twig', [
            'data' => $data,
            'generated_at' => new \DateTime(),
            'filters_applied' => $request ? $request->query->all() : []
        ]);
        
        // Use DomPDF or similar library in production
        // For now, create a proper HTML response that can be printed to PDF
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'inline; filename="mkgov_dashboard_report_' . date('Y-m-d_H-i-s') . '.html"');
        
        return $response;
    }

    /**
     * Export data to Excel format - CORRIGÉ
     */
    private function exportToExcel(array $data): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function() use ($data) {
            $handle = fopen('php://output', 'w+');
            
            // Excel-compatible headers
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            
            // Create Excel-like structure with tabs simulation
            fputcsv($handle, ['=== MK GOV DASHBOARD EXPORT ==='], "\t");
            fputcsv($handle, ['Generated: ' . date('Y-m-d H:i:s')], "\t");
            fputcsv($handle, [], "\t");
            
            // Statistics Sheet
            fputcsv($handle, ['--- STATISTICS ---'], "\t");
            fputcsv($handle, ['Metric', 'Value'], "\t");
            foreach ($data['stats'] as $key => $value) {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', $key)), $value], "\t");
            }
            
            fputcsv($handle, [], "\t");
            fputcsv($handle, ['--- REQUESTS BY FAMILY ---'], "\t");
            fputcsv($handle, ['Family', 'Count', 'Percentage'], "\t");
            foreach ($data['requestsByFamily'] as $family) {
                fputcsv($handle, [
                    $family['family_name'] ?? 'Unassigned',
                    $family['count'],
                    $family['percentage'] . '%'
                ], "\t");
            }
            
            fputcsv($handle, [], "\t");
            fputcsv($handle, ['--- MONTHLY REQUESTS ---'], "\t");
            fputcsv($handle, ['Month', 'Count'], "\t");
            if (isset($data['monthlyRequests']['labels']) && isset($data['monthlyRequests']['data'])) {
                foreach ($data['monthlyRequests']['labels'] as $index => $month) {
                    fputcsv($handle, [$month, $data['monthlyRequests']['data'][$index] ?? 0], "\t");
                }
            }
            
            fputcsv($handle, [], "\t");
            fputcsv($handle, ['--- REQUESTS BY LOCATION ---'], "\t");
            fputcsv($handle, ['Region', 'Count', 'Latitude', 'Longitude'], "\t");
            foreach ($data['requestsByLocation'] as $location) {
                fputcsv($handle, [
                    $location['region'],
                    $location['count'],
                    $location['lat'] ?? '',
                    $location['lng'] ?? ''
                ], "\t");
            }
            
            fclose($handle);
        });
        
        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="mkgov_dashboard_export_' . date('Y-m-d_H-i-s') . '.xls"');
        
        return $response;
    }
}