<?php

namespace App\Controller\Admin;

use App\Entity\Request;
use App\Form\RequestType;
use App\Repository\RequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/request')]
class RequestController extends AbstractController
{
    #[Route('/', name: 'admin_request_index', methods: ['GET'])]
    public function index(RequestRepository $requestRepository, HttpRequest $httpRequest): Response
    {
        $search = $httpRequest->query->get('search', '');
        $status = $httpRequest->query->get('status', '');
        $priority = $httpRequest->query->get('priority', '');
        $procedure = $httpRequest->query->get('procedure', '');
        $family = $httpRequest->query->get('family', '');
        $page = max(1, $httpRequest->query->getInt('page', 1));
        $limit = 20;

        $filters = [
            'search' => $search,
            'status' => $status,
            'priority' => $priority,
            'procedure' => $procedure,
            'family' => $family
        ];

        $requests = $requestRepository->findWithFilters($filters, $page, $limit);
        $statistics = $requestRepository->getStatistics();
        
        return $this->render('admin/request/index.html.twig', [
            'requests' => $requests,
            'filters' => $filters,
            'statistics' => $statistics,
            'currentPage' => $page,
        ]);
    }

    #[Route('/new', name: 'admin_request_new', methods: ['GET', 'POST'])]
    public function new(HttpRequest $httpRequest, EntityManagerInterface $entityManager): Response
    {
        $request = new Request();
        $form = $this->createForm(RequestType::class, $request);
        $form->handleRequest($httpRequest);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Auto-calculate total cost from procedure
                if ($request->getProcedure() && !$request->getTotalCost()) {
                    $request->setTotalCost($request->getProcedure()->getServiceCost());
                }

                // Auto-calculate expected completion date
                if ($request->getProcedure() && !$request->getExpectedCompletionAt()) {
                    $processTime = $request->getProcedure()->getProcessTime();
                    // Extract days from process time (assuming format like "5-7 working days")
                    preg_match('/(\d+)/', $processTime, $matches);
                    $days = isset($matches[1]) ? (int)$matches[1] : 7;
                    
                    $expectedDate = new \DateTime();
                    $expectedDate->add(new \DateInterval('P' . $days . 'D'));
                    $request->setExpectedCompletionAt($expectedDate);
                }

                $entityManager->persist($request);
                $entityManager->flush();

                $this->addFlash('success', 'Request has been created successfully.');
                return $this->redirectToRoute('admin_request_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating request: ' . $e->getMessage());
            }
        }

        return $this->render('admin/request/new.html.twig', [
            'request' => $request,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_request_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        // Check if request is deleted
        if ($request->isDeleted()) {
            throw $this->createNotFoundException('Request not found.');
        }

        return $this->render('admin/request/show.html.twig', [
            'request' => $request,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_request_edit', methods: ['GET', 'POST'])]
    public function edit(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Check if request is deleted
        if ($request->isDeleted()) {
            throw $this->createNotFoundException('Request not found.');
        }

        $form = $this->createForm(RequestType::class, $request);
        $form->handleRequest($httpRequest);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();

                $this->addFlash('success', 'Request has been updated successfully.');
                return $this->redirectToRoute('admin_request_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating request: ' . $e->getMessage());
            }
        }

        return $this->render('admin/request/edit.html.twig', [
            'request' => $request,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_request_delete', methods: ['POST'])]
    public function delete(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$request->getId(), $httpRequest->request->get('_token'))) {
            try {
                // Soft delete - set isDeleted to true instead of removing from database
                $request->setIsDeleted(true);
                $entityManager->flush();

                $this->addFlash('success', 'Request has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting request: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_request_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_request_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $currentStatus = $request->getStatus();
            $newStatus = match($currentStatus) {
                'pending' => 'processing',
                'processing' => 'completed',
                'completed' => 'pending',
                'rejected' => 'pending',
                'cancelled' => 'pending',
                default => 'pending'
            };
            
            $request->setStatus($newStatus);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $request->getStatus(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/update-status', name: 'admin_request_update_status', methods: ['POST'])]
    public function updateStatus(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($httpRequest->getContent(), true);
            $newStatus = $data['status'] ?? null;
            
            if (!in_array($newStatus, ['pending', 'processing', 'completed', 'rejected', 'cancelled'])) {
                throw new \InvalidArgumentException('Invalid status');
            }
            
            $request->setStatus($newStatus);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $request->getStatus(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/update-priority', name: 'admin_request_update_priority', methods: ['POST'])]
    public function updatePriority(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($httpRequest->getContent(), true);
            $newPriority = $data['priority'] ?? null;
            
            if (!in_array($newPriority, ['low', 'normal', 'high', 'urgent'])) {
                throw new \InvalidArgumentException('Invalid priority');
            }
            
            $request->setPriority($newPriority);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'priority' => $request->getPriority(),
                'message' => 'Priority updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating priority: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/search', name: 'admin_request_search', methods: ['GET'])]
    public function search(HttpRequest $httpRequest, RequestRepository $requestRepository): JsonResponse
    {
        $query = $httpRequest->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $requests = $requestRepository->searchByReferenceOrPerson($query, 10);
        
        $results = [];
        foreach ($requests as $request) {
            $results[] = [
                'id' => $request->getId(),
                'text' => sprintf('%s - %s (%s)', $request->getReference(), $request->getPerson()->getFullName(), $request->getProcedure()->getPname()),
                'reference' => $request->getReference(),
                'status' => $request->getStatus(),
                'person' => $request->getPerson()->getFullName(),
                'procedure' => $request->getProcedure()->getPname()
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/export', name: 'admin_request_export', methods: ['GET'])]
    public function export(RequestRepository $requestRepository): Response
    {
        $requests = $requestRepository->findBy(['isDeleted' => false], ['submittedAt' => 'DESC']);
        
        $csvData = [];
        $csvData[] = [
            'Reference', 'Citizen', 'Procedure', 'Status', 'Priority', 
            'Total Cost', 'Paid Amount', 'Payment Status', 'Submitted At', 
            'Expected Completion', 'Completed At', 'Days in Progress'
        ];

        foreach ($requests as $request) {
            $csvData[] = [
                $request->getReference(),
                $request->getPerson()->getFullName(),
                $request->getProcedure()->getPname(),
                $request->getStatus(),
                $request->getPriority(),
                $request->getTotalCost() ?? '0',
                $request->getPaidAmount() ?? '0',
                $request->getPaymentStatus(),
                $request->getSubmittedAt()?->format('Y-m-d H:i:s'),
                $request->getExpectedCompletionAt()?->format('Y-m-d H:i:s'),
                $request->getCompletedAt()?->format('Y-m-d H:i:s'),
                $request->getDaysInProgress()
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="requests_export_'.date('Y-m-d').'.csv"');

        $output = fopen('php://output', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);

        return $response;
    }

    #[Route('/api/procedure/{id}/info', name: 'admin_request_procedure_info', methods: ['GET'])]
    public function getProcedureInfo(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $procedure = $entityManager->getRepository(\App\Entity\Procedure::class)->find($id);
        
        if (!$procedure) {
            return new JsonResponse(['error' => 'Procedure not found'], 404);
        }

        return new JsonResponse([
            'cost' => $procedure->getServiceCost(),
            'processTime' => $procedure->getProcessTime(),
            'family' => $procedure->getFamily()?->getFname()
        ]);
    }
}