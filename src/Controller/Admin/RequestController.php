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
                    preg_match('/(\d+)/', $processTime, $matches);
                    $days = isset($matches[1]) ? (int)$matches[1] : 7;
                    
                    $expectedDate = new \DateTime();
                    $expectedDate->add(new \DateInterval('P' . $days . 'D'));
                    $request->setExpectedCompletionAt($expectedDate);
                }

                // Validate payment amount constraints
                $this->validatePaymentConstraints($request);

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

    #[Route('/export', name: 'admin_request_export', methods: ['GET'])]
    public function export(RequestRepository $requestRepository, HttpRequest $httpRequest): Response
    {
        try {
            $search = $httpRequest->query->get('search', '');
            $status = $httpRequest->query->get('status', '');
            $priority = $httpRequest->query->get('priority', '');
            $procedure = $httpRequest->query->get('procedure', '');
            $family = $httpRequest->query->get('family', '');

            $filters = [
                'search' => $search,
                'status' => $status,
                'priority' => $priority,
                'procedure' => $procedure,
                'family' => $family
            ];

            $requests = array_filter($filters) ? 
                $requestRepository->findWithFilters($filters) : 
                $requestRepository->findBy(['isDeleted' => false], ['submittedAt' => 'DESC']);
            
            $csvData = [];
            $csvData[] = [
                'Reference', 'Citizen', 'National ID', 'Procedure', 'Family', 'Status', 'Priority', 
                'Total Cost (XAF)', 'Paid Amount (XAF)', 'Payment Status', 'Remaining Amount (XAF)',
                'Submitted At', 'Expected Completion', 'Completed At', 'Days in Progress', 'Comments'
            ];

            foreach ($requests as $request) {
                $csvData[] = [
                    $request->getReference(),
                    $request->getPerson()->getFullName(),
                    $request->getPerson()->getNationalId(),
                    $request->getProcedure()->getPname(),
                    $request->getProcedure()->getFamily() ? $request->getProcedure()->getFamily()->getFname() : '',
                    ucfirst($request->getStatus()),
                    ucfirst($request->getPriority()),
                    $request->getTotalCost() ?? '0',
                    $request->getPaidAmount() ?? '0',
                    $request->getPaymentStatusLabel(),
                    $request->getRemainingAmount(),
                    $request->getSubmittedAt()?->format('Y-m-d H:i:s'),
                    $request->getExpectedCompletionAt()?->format('Y-m-d H:i:s'),
                    $request->getCompletedAt()?->format('Y-m-d H:i:s'),
                    $request->getDaysInProgress(),
                    $request->getComments() ? strip_tags($request->getComments()) : ''
                ];
            }

            $filename = 'requests_export_' . date('Y-m-d_H-i-s') . '.csv';
            $response = new Response();
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->headers->set('Cache-Control', 'must-revalidate');
            $response->headers->set('Pragma', 'public');

            $csvContent = "\xEF\xBB\xBF";
            $output = fopen('php://temp', 'w');
            foreach ($csvData as $row) {
                fputcsv($output, $row, ';');
            }
            rewind($output);
            $csvContent .= stream_get_contents($output);
            fclose($output);

            $response->setContent($csvContent);
            return $response;
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error exporting data: ' . $e->getMessage());
            return $this->redirectToRoute('admin_request_index');
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

    #[Route('/{id}', name: 'admin_request_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Request $request): Response
    {
        if ($request->isDeleted()) {
            throw $this->createNotFoundException('Request not found.');
        }

        return $this->render('admin/request/show.html.twig', [
            'request' => $request,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_request_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isDeleted()) {
            throw $this->createNotFoundException('Request not found.');
        }

        // Store original status and payment data for comparison
        $originalStatus = $request->getStatus();
        $originalPaidAmount = $request->getPaidAmount();

        $form = $this->createForm(RequestType::class, $request);
        $form->handleRequest($httpRequest);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Validate payment constraints
                $this->validatePaymentConstraints($request);

                // Handle status changes and payment logic
                $this->handleStatusChanges($request, $originalStatus, $originalPaidAmount);

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

    #[Route('/{id}/delete', name: 'admin_request_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$request->getId(), $httpRequest->request->get('_token'))) {
            try {
                $request->setIsDeleted(true);
                $entityManager->flush();

                $this->addFlash('success', 'Request has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting request: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_request_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_request_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
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
            
            $originalPaidAmount = $request->getPaidAmount();
            $request->setStatus($newStatus);
            
            // Handle payment status updates based on status change
            $this->handleStatusChanges($request, $currentStatus, $originalPaidAmount);
            
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $request->getStatus(),
                'paymentStatus' => $request->getPaymentStatus(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/update-status', name: 'admin_request_update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(HttpRequest $httpRequest, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $data = json_decode($httpRequest->getContent(), true);
            $newStatus = $data['status'] ?? null;
            
            if (!in_array($newStatus, ['pending', 'processing', 'completed', 'rejected', 'cancelled'])) {
                throw new \InvalidArgumentException('Invalid status');
            }
            
            $originalStatus = $request->getStatus();
            $originalPaidAmount = $request->getPaidAmount();
            
            $request->setStatus($newStatus);
            
            // Handle payment status updates based on status change
            $this->handleStatusChanges($request, $originalStatus, $originalPaidAmount);
            
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $request->getStatus(),
                'paymentStatus' => $request->getPaymentStatus(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/update-priority', name: 'admin_request_update_priority', methods: ['POST'], requirements: ['id' => '\d+'])]
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

    #[Route('/{id}/info', name: 'admin_request_info', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getRequestInfo(Request $request): JsonResponse
    {
        try {
            return new JsonResponse([
                'success' => true,
                'request_id' => $request->getId(),
                'request_reference' => $request->getReference(),
                'request_status' => $request->getStatus(),
                'procedure_id' => $request->getProcedure()?->getId(),
                'procedure_name' => $request->getProcedure()?->getPname(),
                'family_name' => $request->getProcedure()?->getFamily()?->getFname(),
                'person_id' => $request->getPerson()?->getId(),
                'person_name' => $request->getPerson()?->getFullName(),
                'person_national_id' => $request->getPerson()?->getNationalId(),
                'submitted_at' => $request->getSubmittedAt()?->format('Y-m-d H:i:s'),
                'expected_completion_at' => $request->getExpectedCompletionAt()?->format('Y-m-d H:i:s'),
                'total_cost' => $request->getTotalCost(),
                'paid_amount' => $request->getPaidAmount(),
                'remaining_amount' => $request->getRemainingAmount(),
                'payment_status' => $request->getPaymentStatus(),
                'priority' => $request->getPriority(),
                'can_modify_payment' => $request->canModifyPayment(),
                'can_modify_timeline' => $request->canModifyTimeline()
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error retrieving request information: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/documents', name: 'admin_request_documents', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getRequestDocuments(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $documents = $entityManager->getRepository(\App\Entity\Document::class)->findByRequest($request);
            
            $result = [];
            foreach ($documents as $document) {
                $result[] = [
                    'id' => $document->getId(),
                    'name' => $document->getName(),
                    'type' => $document->getType(),
                    'status' => $document->getStatus(),
                    'isRequired' => $document->isRequired(),
                    'filePath' => $document->getFilePath(),
                    'fileSize' => $document->getFileSize(),
                    'mimeType' => $document->getMimeType(),
                    'expirationDate' => $document->getExpirationDate()?->format('Y-m-d'),
                    'createdAt' => $document->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'url' => $this->generateUrl('admin_document_show', ['id' => $document->getId()])
                ];
            }

            return new JsonResponse([
                'success' => true,
                'documents' => $result,
                'count' => count($result)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error retrieving documents: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/add-document', name: 'admin_request_add_document', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function addDocument(Request $request): Response
    {
        return $this->redirectToRoute('admin_document_new', [
            'request' => $request->getId()
        ]);
    }

    /**
     * Valide les contraintes de paiement
     */
    private function validatePaymentConstraints(Request $request): void
    {
        $totalCost = (float) ($request->getTotalCost() ?? 0);
        $paidAmount = (float) ($request->getPaidAmount() ?? 0);

        // Vérifier que le montant payé n'est pas négatif
        if ($paidAmount < 0) {
            throw new \InvalidArgumentException('Paid amount cannot be negative');
        }

        // Vérifier que le montant payé ne dépasse pas le coût total
        if ($paidAmount > $totalCost) {
            throw new \InvalidArgumentException(
                sprintf('Paid amount (%.2f XAF) cannot exceed total cost (%.2f XAF)', $paidAmount, $totalCost)
            );
        }
    }

    /**
     * Gère les changements de statut et leurs impacts sur les paiements
     */
    private function handleStatusChanges(Request $request, string $originalStatus, ?string $originalPaidAmount): void
    {
        $currentStatus = $request->getStatus();
        $currentPaidAmount = (float) ($request->getPaidAmount() ?? 0);

        // Si le statut change vers rejected ou cancelled
        if (in_array($currentStatus, ['rejected', 'cancelled']) && !in_array($originalStatus, ['rejected', 'cancelled'])) {
            if ($currentPaidAmount > 0) {
                $request->setPaymentStatus('awaiting_refund');
            } else {
                $request->setPaymentStatus('revoked');
            }
        }
        // Si le statut change depuis rejected/cancelled vers un statut actif
        elseif (!in_array($currentStatus, ['rejected', 'cancelled']) && in_array($originalStatus, ['rejected', 'cancelled'])) {
            // Remettre un statut de paiement normal basé sur les montants
            $totalCost = (float) ($request->getTotalCost() ?? 0);
            
            if ($currentPaidAmount === 0) {
                $request->setPaymentStatus('pending');
            } elseif ($currentPaidAmount >= $totalCost) {
                $request->setPaymentStatus('completed');
            } else {
                $request->setPaymentStatus('partial');
            }
        }
        // Pour les requêtes qui restent dans un statut actif
        elseif (!in_array($currentStatus, ['rejected', 'cancelled'])) {
            // Mettre à jour automatiquement le statut de paiement si ce n'est pas un statut spécial
            if (!in_array($request->getPaymentStatus(), ['awaiting_refund', 'revoked', 'refunded'])) {
                $totalCost = (float) ($request->getTotalCost() ?? 0);
                
                if ($currentPaidAmount === 0) {
                    $request->setPaymentStatus('pending');
                } elseif ($currentPaidAmount >= $totalCost) {
                    $request->setPaymentStatus('completed');
                } else {
                    $request->setPaymentStatus('partial');
                }
            }
        }
    }
}