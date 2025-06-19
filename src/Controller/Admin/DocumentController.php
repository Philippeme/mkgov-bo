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
    public function index(DocumentRepository $documentRepository): Response
    {
        $documents = $documentRepository->createQueryBuilder('d')
            ->leftJoin('d.procedure', 'p')
            ->addSelect('p')
            ->orderBy('d.type', 'ASC')
            ->addOrderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/document/index.html.twig', [
            'documents' => $documents,
        ]);
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $document = new Document();
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
                }

                // Handle custom document upload
                $documentFile = $form->get('documentFile')->getData();
                if ($documentFile) {
                    // Récupérer les infos AVANT de déplacer le fichier
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

                $entityManager->persist($document);
                $entityManager->flush();

                $this->addFlash('success', 'Document has been created successfully.');
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
        return $this->render('admin/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_document_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
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

                        $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$documentFile->guessExtension();

                        $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $documentFile->move($uploadsDirectory, $newFilename);
                        $document->setFilePath($newFilename);
                        $document->setFileSize($this->formatFileSize($documentFile->getSize()));
                        $document->setMimeType($documentFile->getMimeType());
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

    private function isDocumentTemplate(string $filename): bool
    {
        $templateFiles = [
            'birth-certificate-template.pdf', 'national-id-template.pdf', 'passport-template.pdf',
            'marriage-certificate-template.pdf', 'death-certificate-template.pdf', 'divorce-certificate-template.pdf',
            'business-license-template.pdf', 'tax-certificate-template.pdf', 'commercial-registration-template.pdf',
            'diploma-certificate-template.pdf', 'transcript-template.pdf', 'equivalence-certificate-template.pdf',
            'criminal-background-template.pdf', 'legal-certificate-template.pdf', 'court-document-template.pdf',
            'medical-certificate-template.pdf', 'health-permit-template.pdf', 'vaccination-record-template.pdf'
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