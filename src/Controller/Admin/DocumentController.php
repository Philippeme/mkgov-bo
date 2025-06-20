<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/document')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'admin_document_index', methods: ['GET'])]
    public function index(DocumentRepository $documentRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');
        $expiring = $request->query->get('expiring', '');

        $queryBuilder = $documentRepository->createQueryBuilder('d')
            ->leftJoin('d.procedure', 'p')
            ->leftJoin('d.person', 'per')
            ->addSelect('p', 'per')
            ->where('d.isActive = :active')
            ->setParameter('active', true);

        if ($search) {
            $queryBuilder->andWhere('d.name LIKE :search OR d.description LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            $queryBuilder->andWhere('d.type = :type')
                        ->setParameter('type', $type);
        }

        if ($status) {
            $queryBuilder->andWhere('d.status = :status')
                        ->setParameter('status', $status);
        }

        if ($expiring === 'yes') {
            $futureDate = new \DateTime();
            $futureDate->add(new \DateInterval('P30D'));
            $queryBuilder->andWhere('d.expirationDate BETWEEN :today AND :futureDate')
                        ->setParameter('today', new \DateTime())
                        ->setParameter('futureDate', $futureDate);
        } elseif ($expiring === 'expired') {
            $queryBuilder->andWhere('d.expirationDate < :today')
                        ->setParameter('today', new \DateTime());
        }

        $documents = $queryBuilder->orderBy('d.type', 'ASC')
                                 ->addOrderBy('d.displayOrder', 'ASC')
                                 ->addOrderBy('d.createdAt', 'DESC')
                                 ->getQuery()
                                 ->getResult();
        
        return $this->render('admin/document/index.html.twig', [
            'documents' => $documents,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'expiring' => $expiring
            ]
        ]);
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $document = new Document();
        
        // If person parameter is provided, set the person
        $personId = $request->query->get('person');
        if ($personId) {
            $person = $entityManager->getRepository(\App\Entity\Person::class)->find($personId);
            if ($person && !$person->isDeleted()) {
                $document->setPerson($person);
            }
        }

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle document template selection
                $documentTemplate = $form->get('documentTemplates')->getData();
                if ($documentTemplate) {
                    $this->generateDocumentTemplate($documentTemplate);
                    $document->setFilePath($documentTemplate);
                    $document->setMimeType('application/pdf');
                    $document->setFileSize('Template');
                }

                // Handle custom document upload
                $documentFile = $form->get('documentFile')->getData();
                if ($documentFile) {
                    $fileSize = $documentFile->getSize();
                    $mimeType = $documentFile->getMimeType();
                    
                    $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$documentFile->guessExtension();

                    $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0755, true);
                    }
                    
                    $documentFile->move($uploadsDirectory, $newFilename);
                    $document->setFilePath($newFilename);
                    $document->setFileSize($this->formatFileSize($fileSize));
                    $document->setMimeType($mimeType);
                }

                // Validate expiration date
                if ($document->getExpirationDate() && $document->getExpirationDate() <= new \DateTime()) {
                    $this->addFlash('warning', 'Document created with past expiration date. Please review.');
                }

                // Auto-update status based on expiration
                if ($document->getExpirationDate() && $document->getExpirationDate() <= new \DateTime()) {
                    $document->setStatus('expired');
                }

                $entityManager->persist($document);
                $entityManager->flush();

                $this->addFlash('success', 'Document has been created successfully.');
                
                // Redirect based on context
                if ($document->getPerson()) {
                    return $this->redirectToRoute('admin_person_show', ['id' => $document->getPerson()->getId()]);
                }
                
                return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating document: ' . $e->getMessage());
            }
        }

        return $this->render('admin/document/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_document_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        if (!$document->isActive()) {
            throw $this->createNotFoundException('Document not found.');
        }

        return $this->render('admin/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_document_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (!$document->isActive()) {
            throw $this->createNotFoundException('Document not found.');
        }

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle document removal
                $removeDocument = $form->get('removeDocument')->getData();
                if ($removeDocument) {
                    if ($document->getFilePath() && !$this->isDocumentTemplate($document->getFilePath())) {
                        $oldDocPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$document->getFilePath();
                        if (file_exists($oldDocPath)) {
                            unlink($oldDocPath);
                        }
                    }
                    $document->setFilePath(null);
                    $document->setFileSize(null);
                    $document->setMimeType(null);
                } else {
                    // Handle document template selection
                    $documentTemplate = $form->get('documentTemplates')->getData();
                    if ($documentTemplate) {
                        if ($document->getFilePath() && !$this->isDocumentTemplate($document->getFilePath())) {
                            $oldDocPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$document->getFilePath();
                            if (file_exists($oldDocPath)) {
                                unlink($oldDocPath);
                            }
                        }
                        $this->generateDocumentTemplate($documentTemplate);
                        $document->setFilePath($documentTemplate);
                        $document->setMimeType('application/pdf');
                        $document->setFileSize('Template');
                    }

                    // Handle custom document upload
                    $documentFile = $form->get('documentFile')->getData();
                    if ($documentFile) {
                        if ($document->getFilePath() && !$this->isDocumentTemplate($document->getFilePath())) {
                            $oldDocPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$document->getFilePath();
                            if (file_exists($oldDocPath)) {
                                unlink($oldDocPath);
                            }
                        }

                        $fileSize = $documentFile->getSize();
                        $mimeType = $documentFile->getMimeType();
                        
                        $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$documentFile->guessExtension();

                        $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $documentFile->move($uploadsDirectory, $newFilename);
                        $document->setFilePath($newFilename);
                        $document->setFileSize($this->formatFileSize($fileSize));
                        $document->setMimeType($mimeType);
                    }
                }

                // Auto-update status based on expiration
                if ($document->getExpirationDate()) {
                    if ($document->getExpirationDate() <= new \DateTime()) {
                        $document->setStatus('expired');
                        $this->addFlash('warning', 'Document status updated to expired due to past expiration date.');
                    } elseif ($document->getStatus() === 'expired' && $document->getExpirationDate() > new \DateTime()) {
                        $document->setStatus('active');
                        $this->addFlash('info', 'Document status updated from expired to active due to future expiration date.');
                    }
                }

                $entityManager->flush();

                $this->addFlash('success', 'Document has been updated successfully.');
                return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating document: ' . $e->getMessage());
            }
        }

        return $this->render('admin/document/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            try {
                // Delete custom file if exists
                if ($document->getFilePath() && !$this->isDocumentTemplate($document->getFilePath())) {
                    $filePath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$document->getFilePath();
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }

                // Soft delete by setting isActive to false
                $document->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Document has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting document: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_document_toggle_status', methods: ['POST'])]
    public function toggleStatus(Document $document, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $document->setIsActive(!$document->isActive());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $document->isActive(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/update-status', name: 'admin_document_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Document $document, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $newStatus = $request->request->get('status');
            $validStatuses = ['draft', 'pending', 'approved', 'rejected', 'expired', 'active'];
            
            if (!in_array($newStatus, $validStatuses)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid status'
                ], 400);
            }

            $document->setStatus($newStatus);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $newStatus,
                'badgeClass' => $document->getStatusBadgeClass(),
                'message' => 'Document status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/expiring-soon', name: 'admin_document_expiring_soon', methods: ['GET'])]
    public function expiringSoon(DocumentRepository $documentRepository): JsonResponse
    {
        $documents = $documentRepository->findExpiringSoon(30);
        
        $result = [];
        foreach ($documents as $document) {
            $result[] = [
                'id' => $document->getId(),
                'name' => $document->getName(),
                'expirationDate' => $document->getExpirationDate()?->format('Y-m-d'),
                'daysUntilExpiry' => $document->getExpirationDate() ? 
                    (new \DateTime())->diff($document->getExpirationDate())->days : null,
                'person' => $document->getPerson() ? $document->getPerson()->getFullName() : null,
                'procedure' => $document->getProcedure() ? $document->getProcedure()->getPname() : null
            ];
        }

        return new JsonResponse($result);
    }

    private function isDocumentTemplate(string $filename): bool
    {
        $templateFiles = [
            'birth-certificate-template.pdf', 'national-id-template.pdf', 'passport-template.pdf',
            'nationality-certificate-template.pdf', 'marriage-certificate-template.pdf', 
            'death-certificate-template.pdf', 'divorce-certificate-template.pdf',
            'business-license-template.pdf', 'tax-certificate-template.pdf', 
            'commercial-registration-template.pdf', 'import-license-template.pdf',
            'export-license-template.pdf', 'diploma-certificate-template.pdf', 
            'transcript-template.pdf', 'equivalence-certificate-template.pdf',
            'translation-certificate-template.pdf', 'criminal-background-template.pdf', 
            'legal-certificate-template.pdf', 'court-document-template.pdf',
            'loss-declaration-template.pdf', 'medical-certificate-template.pdf', 
            'health-permit-template.pdf', 'vaccination-record-template.pdf',
            'driving-license-template.pdf', 'vehicle-registration-template.pdf',
            'transport-license-template.pdf', 'land-title-template.pdf',
            'building-permit-template.pdf', 'property-certificate-template.pdf'
        ];
        
        return in_array($filename, $templateFiles);
    }

    private function generateDocumentTemplate(string $templateName): void
    {
        $docPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$templateName;
        
        if (!file_exists($docPath)) {
            $pdfContent = $this->generateTemplatePdf($templateName);
            file_put_contents($docPath, $pdfContent);
        }
    }

    private function generateTemplatePdf(string $filename): string
    {
        $title = str_replace(['-template.pdf', '-'], ['', ' '], $filename);
        $title = ucwords($title);

        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
               "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
               "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n" .
               "4 0 obj\n<< /Length 85 >>\nstream\nBT\n/F1 12 Tf\n100 750 Td\n" .
               "(Document Template: {$title}) Tj\n0 -30 Td\n(Generated by MK Gov System) Tj\nET\nendstream\nendobj\n" .
               "xref\n0 5\n0000000000 65535 f \ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n%%EOF";
    }

    private function formatFileSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        return round($size, 2) . ' ' . $units[$unit];
    }
}