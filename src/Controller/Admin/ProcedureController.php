<?php

namespace App\Controller\Admin;

use App\Entity\Procedure;
use App\Form\ProcedureType;
use App\Repository\ProcedureRepository;
use App\Repository\PublicEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/procedure')]
class ProcedureController extends AbstractController
{
    #[Route('/', name: 'admin_procedure_index', methods: ['GET'])]
    public function index(ProcedureRepository $procedureRepository): Response
    {
        $procedures = $procedureRepository->createQueryBuilder('p')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.providingAdministration', 'pa')
            ->leftJoin('pa.department', 'd')
            ->leftJoin('p.documents', 'doc')
            ->addSelect('f', 'pa', 'd', 'doc')
            ->orderBy('p.displayOrder', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/procedure/index.html.twig', [
            'procedures' => $procedures,
        ]);
    }

    #[Route('/new', name: 'admin_procedure_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $procedure = new Procedure();
        $form = $this->createForm(ProcedureType::class, $procedure);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle Bootstrap image template
                $imageBootstrap = $form->get('imageBootstrap')->getData();
                if ($imageBootstrap) {
                    $this->generateBootstrapImage($imageBootstrap);
                    $procedure->setImage($imageBootstrap);
                }

                // Handle custom image upload
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                    $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/procedures';
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0755, true);
                    }
                    
                    $imageFile->move($uploadsDirectory, $newFilename);
                    $procedure->setImage($newFilename);
                }

                // Handle legal text template
                $legalTextTemplate = $form->get('legalTextTemplate')->getData();
                if ($legalTextTemplate) {
                    $this->generateLegalTextTemplate($legalTextTemplate);
                    $procedure->setLegalText($legalTextTemplate);
                }

                // Handle custom legal text upload
                $legalTextFile = $form->get('legalTextFile')->getData();
                if ($legalTextFile) {
                    $originalFilename = pathinfo($legalTextFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$legalTextFile->guessExtension();

                    $documentsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
                    if (!is_dir($documentsDirectory)) {
                        mkdir($documentsDirectory, 0755, true);
                    }
                    
                    $legalTextFile->move($documentsDirectory, $newFilename);
                    $procedure->setLegalText($newFilename);
                }

                $entityManager->persist($procedure);
                $entityManager->flush();

                $this->addFlash('success', 'Procedure has been created successfully.');
                return $this->redirectToRoute('admin_procedure_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating procedure: ' . $e->getMessage());
            }
        }

        return $this->render('admin/procedure/new.html.twig', [
            'procedure' => $procedure,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_procedure_show', methods: ['GET'])]
    public function show(Procedure $procedure): Response
    {
        return $this->render('admin/procedure/show.html.twig', [
            'procedure' => $procedure,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_procedure_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Procedure $procedure, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProcedureType::class, $procedure);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle image removal
                $removeImage = $form->get('removeImage')->getData();
                if ($removeImage) {
                    if ($procedure->getImage() && !$this->isBootstrapTemplate($procedure->getImage())) {
                        $oldImagePath = $this->getParameter('kernel.project_dir').'/public/uploads/procedures/'.$procedure->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    $procedure->setImage(null);
                } else {
                    // Handle Bootstrap image template
                    $imageBootstrap = $form->get('imageBootstrap')->getData();
                    if ($imageBootstrap) {
                        if ($procedure->getImage() && !$this->isBootstrapTemplate($procedure->getImage())) {
                            $oldImagePath = $this->getParameter('kernel.project_dir').'/public/uploads/procedures/'.$procedure->getImage();
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        $this->generateBootstrapImage($imageBootstrap);
                        $procedure->setImage($imageBootstrap);
                    }

                    // Handle custom image upload
                    $imageFile = $form->get('imageFile')->getData();
                    if ($imageFile) {
                        if ($procedure->getImage() && !$this->isBootstrapTemplate($procedure->getImage())) {
                            $oldImagePath = $this->getParameter('kernel.project_dir').'/public/uploads/procedures/'.$procedure->getImage();
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }

                        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                        $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/procedures';
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $imageFile->move($uploadsDirectory, $newFilename);
                        $procedure->setImage($newFilename);
                    }
                }

                // Handle legal text removal
                $removeLegalText = $form->get('removeLegalText')->getData();
                if ($removeLegalText) {
                    if ($procedure->getLegalText() && !$this->isBootstrapTemplate($procedure->getLegalText())) {
                        $oldDocPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$procedure->getLegalText();
                        if (file_exists($oldDocPath)) {
                            unlink($oldDocPath);
                        }
                    }
                    $procedure->setLegalText(null);
                } else {
                    // Handle legal text template
                    $legalTextTemplate = $form->get('legalTextTemplate')->getData();
                    if ($legalTextTemplate) {
                        if ($procedure->getLegalText() && !$this->isBootstrapTemplate($procedure->getLegalText())) {
                            $oldDocPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$procedure->getLegalText();
                            if (file_exists($oldDocPath)) {
                                unlink($oldDocPath);
                            }
                        }
                        $this->generateLegalTextTemplate($legalTextTemplate);
                        $procedure->setLegalText($legalTextTemplate);
                    }

                    // Handle custom legal text upload
                    $legalTextFile = $form->get('legalTextFile')->getData();
                    if ($legalTextFile) {
                        if ($procedure->getLegalText() && !$this->isBootstrapTemplate($procedure->getLegalText())) {
                            $oldDocPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$procedure->getLegalText();
                            if (file_exists($oldDocPath)) {
                                unlink($oldDocPath);
                            }
                        }

                        $originalFilename = pathinfo($legalTextFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$legalTextFile->guessExtension();

                        $documentsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/documents';
                        if (!is_dir($documentsDirectory)) {
                            mkdir($documentsDirectory, 0755, true);
                        }
                        
                        $legalTextFile->move($documentsDirectory, $newFilename);
                        $procedure->setLegalText($newFilename);
                    }
                }

                $entityManager->flush();

                $this->addFlash('success', 'Procedure has been updated successfully.');
                return $this->redirectToRoute('admin_procedure_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating procedure: ' . $e->getMessage());
            }
        }

        return $this->render('admin/procedure/edit.html.twig', [
            'procedure' => $procedure,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_procedure_delete', methods: ['POST'])]
    public function delete(Request $request, Procedure $procedure, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$procedure->getId(), $request->request->get('_token'))) {
            try {
                // Delete custom files if they exist
                if ($procedure->getImage() && !$this->isBootstrapTemplate($procedure->getImage())) {
                    $imagePath = $this->getParameter('kernel.project_dir').'/public/uploads/procedures/'.$procedure->getImage();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                if ($procedure->getLegalText() && !$this->isBootstrapTemplate($procedure->getLegalText())) {
                    $docPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$procedure->getLegalText();
                    if (file_exists($docPath)) {
                        unlink($docPath);
                    }
                }

                // Soft delete by setting isActive to false
                $procedure->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Procedure has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting procedure: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_procedure_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_procedure_toggle_status', methods: ['POST'])]
    public function toggleStatus(Procedure $procedure, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $procedure->setIsActive(!$procedure->isActive());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $procedure->isActive(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/toggle-published', name: 'admin_procedure_toggle_published', methods: ['POST'])]
    public function togglePublished(Procedure $procedure, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $procedure->setPublished(!$procedure->isPublished());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'published' => $procedure->isPublished(),
                'message' => 'Publication status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating publication status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/by-administration/{id}', name: 'admin_procedure_by_administration', methods: ['GET'])]
    public function byAdministration(int $id, ProcedureRepository $procedureRepository, PublicEntityRepository $publicEntityRepository): Response
    {
        $administration = $publicEntityRepository->find($id);
        
        if (!$administration) {
            throw $this->createNotFoundException('Public entity not found');
        }

        $procedures = $procedureRepository->findByProvidingAdministration($administration);

        return $this->render('admin/procedure/by_administration.html.twig', [
            'administration' => $administration,
            'procedures' => $procedures,
        ]);
    }

    #[Route('/without-administration', name: 'admin_procedure_without_administration', methods: ['GET'])]
    public function withoutAdministration(ProcedureRepository $procedureRepository): Response
    {
        $procedures = $procedureRepository->findWithoutProvidingAdministration();

        return $this->render('admin/procedure/without_administration.html.twig', [
            'procedures' => $procedures,
        ]);
    }

    #[Route('/filter', name: 'admin_procedure_filter', methods: ['GET'])]
    public function filter(Request $request, ProcedureRepository $procedureRepository): JsonResponse
    {
        $filters = [
            'family' => $request->query->get('family'),
            'administration' => $request->query->get('administration'),
            'department' => $request->query->get('department'),
            'search' => $request->query->get('search')
        ];

        $procedures = $procedureRepository->findPublishedProceduresWithFilters($filters);

        $data = [];
        foreach ($procedures as $procedure) {
            $data[] = [
                'id' => $procedure->getId(),
                'pname' => $procedure->getPname(),
                'family' => $procedure->getFamily() ? $procedure->getFamily()->getFname() : null,
                'administration' => $procedure->getProvidingAdministration() ? $procedure->getProvidingAdministration()->getInstitutionName() : null,
                'department' => $procedure->getProvidingAdministration() && $procedure->getProvidingAdministration()->getDepartment() ? $procedure->getProvidingAdministration()->getDepartment()->getName() : null,
                'servicecost' => $procedure->getServiceCost(),
                'processtime' => $procedure->getProcessTime(),
                'isPublished' => $procedure->isPublished(),
                'isActive' => $procedure->isActive()
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/statistics', name: 'admin_procedure_statistics', methods: ['GET'])]
    public function statistics(ProcedureRepository $procedureRepository): JsonResponse
    {
        $stats = $procedureRepository->getProcedureStats();
        $administrationCounts = $procedureRepository->countByProvidingAdministration();
        $familyData = $procedureRepository->findUniqueFamilies();
        $administrationData = $procedureRepository->findUniqueProvidingAdministrations();

        return new JsonResponse([
            'general_stats' => $stats,
            'administration_counts' => $administrationCounts,
            'families' => $familyData,
            'administrations' => $administrationData
        ]);
    }

    #[Route('/assign-administration', name: 'admin_procedure_assign_administration', methods: ['POST'])]
    public function assignAdministration(Request $request, EntityManagerInterface $entityManager, ProcedureRepository $procedureRepository, PublicEntityRepository $publicEntityRepository): JsonResponse
    {
        try {
            $procedureId = $request->request->get('procedure_id');
            $administrationId = $request->request->get('administration_id');

            $procedure = $procedureRepository->find($procedureId);
            if (!$procedure) {
                return new JsonResponse(['success' => false, 'message' => 'Procedure not found'], 404);
            }

            if ($administrationId) {
                $administration = $publicEntityRepository->find($administrationId);
                if (!$administration) {
                    return new JsonResponse(['success' => false, 'message' => 'Administration not found'], 404);
                }
                $procedure->setProvidingAdministration($administration);
            } else {
                $procedure->setProvidingAdministration(null);
            }

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Administration assignment updated successfully.',
                'administration' => $procedure->getProvidingAdministration() ? $procedure->getProvidingAdministration()->getInstitutionName() : null
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating administration assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    private function isBootstrapTemplate(string $filename): bool
    {
        $templateFiles = [
            'passport-template.jpg', 'id-card-template.jpg', 'birth-certificate-template.jpg',
            'marriage-certificate-template.jpg', 'death-certificate-template.jpg', 'driving-license-template.jpg',
            'diploma-template.jpg', 'business-license-template.jpg', 'land-title-template.jpg',
            'visa-template.jpg', 'medical-authorization-template.jpg', 'general-document-template.jpg',
            'legal-police-justice.pdf', 'legal-civil-status.pdf', 'legal-transport.pdf',
            'legal-education.pdf', 'legal-business.pdf', 'legal-public-service.pdf',
            'legal-land-construction.pdf', 'legal-consular.pdf', 'legal-health.pdf', 'legal-civic-life.pdf'
        ];
        
        return in_array($filename, $templateFiles);
    }

    private function generateBootstrapImage(string $templateName): void
    {
        $imagePath = $this->getParameter('kernel.project_dir').'/public/uploads/procedures/'.$templateName;
        
        if (!file_exists($imagePath)) {
            $templateColors = [
                'passport-template.jpg' => '#e74c3c',
                'id-card-template.jpg' => '#3498db',
                'birth-certificate-template.jpg' => '#2ecc71',
                'marriage-certificate-template.jpg' => '#e91e63',
                'death-certificate-template.jpg' => '#34495e',
                'driving-license-template.jpg' => '#f39c12',
                'diploma-template.jpg' => '#9b59b6',
                'business-license-template.jpg' => '#1abc9c',
                'land-title-template.jpg' => '#e67e22',
                'visa-template.jpg' => '#8e44ad',
                'medical-authorization-template.jpg' => '#16a085',
                'general-document-template.jpg' => '#7f8c8d'
            ];

            $color = $templateColors[$templateName] ?? '#95a5a6';
            $title = str_replace(['-template.jpg', '-'], ['', ' '], $templateName);
            $title = ucwords($title);

            $svg = $this->generateTemplateSvg($title, $color);
            file_put_contents($imagePath, $svg);
        }
    }

    private function generateLegalTextTemplate(string $templateName): void
    {
        $docPath = $this->getParameter('kernel.project_dir').'/public/uploads/documents/'.$templateName;
        
        if (!file_exists($docPath)) {
            $pdfContent = $this->generateTemplatePdf($templateName);
            file_put_contents($docPath, $pdfContent);
        }
    }

    private function generateTemplateSvg(string $title, string $color): string
    {
        return <<<SVG
<svg width="400" height="300" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" style="stop-color:{$color};stop-opacity:0.8" />
            <stop offset="100%" style="stop-color:{$color};stop-opacity:0.4" />
        </linearGradient>
    </defs>
    <rect width="400" height="300" fill="url(#grad)"/>
    <rect x="50" y="50" width="300" height="200" rx="10" fill="white" opacity="0.9"/>
    <text x="200" y="120" font-family="Arial" font-size="18" font-weight="bold" text-anchor="middle" fill="{$color}">{$title}</text>
    <text x="200" y="150" font-family="Arial" font-size="14" text-anchor="middle" fill="#666">Official Document Template</text>
    <rect x="80" y="180" width="240" height="2" fill="{$color}" opacity="0.3"/>
</svg>
SVG;
    }

    private function generateTemplatePdf(string $filename): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
               "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
               "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n" .
               "4 0 obj\n<< /Length 55 >>\nstream\nBT\n/F1 12 Tf\n100 700 Td\n" .
               "(Legal Framework Template: {$filename}) Tj\nET\nendstream\nendobj\n" .
               "xref\n0 5\n0000000000 65535 f \ntrailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n%%EOF";
    }
}