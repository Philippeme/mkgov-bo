<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Entity\ProjectTranslation;
use App\Entity\ProjectMember;
use App\Entity\ProjectLink;
use App\Entity\ProjectAttachment;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin/project')]
#[IsGranted('ROLE_ADMIN')]
class ProjectController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private SluggerInterface $slugger
    ) {}

    #[Route('/', name: 'admin_project_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale() ?: 'fr';
        $searchTerm = $request->query->get('search');
        $filters = $request->query->all();

        if ($searchTerm) {
            $projects = $this->projectRepository->searchProjects($searchTerm, $locale);
        } elseif (!empty(array_filter($filters, fn($v) => $v !== '' && $v !== null))) {
            $projects = $this->projectRepository->filterProjects($filters, $locale);
        } else {
            $projects = $this->projectRepository->findAllWithTranslations($locale);
        }

        $stats = $this->projectRepository->getGlobalStats();
        $statusCounts = $this->projectRepository->countByStatus();
        $categoryCounts = $this->projectRepository->countByCategory();

        return $this->render('admin/project/index.html.twig', [
            'projects' => $projects,
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'categoryCounts' => $categoryCounts,
            'currentLocale' => $locale
        ]);
    }

    #[Route('/new', name: 'admin_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $project = new Project();
        $currentLocale = $request->get('locale', $request->getLocale() ?: 'fr');
        
        $form = $this->createForm(ProjectType::class, $project, [
            'current_locale' => $currentLocale
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier l'unicité du code
            if (!$this->projectRepository->isCodeUnique($project->getCode())) {
                $this->addFlash('error', 'Ce code projet existe déjà.');
                return $this->render('admin/project/new.html.twig', [
                    'project' => $project,
                    'form' => $form->createView(),
                    'currentLocale' => $currentLocale
                ]);
            }

            // Traitement de la traduction pour la langue courante
            $translationData = $request->request->all('translation');
            if (!empty($translationData['name'])) {
                $translation = new ProjectTranslation();
                $translation->setProject($project)
                          ->setLocale($currentLocale)
                          ->setName($translationData['name'])
                          ->setDescription($translationData['description'] ?? '');
                
                $project->addTranslation($translation);
            }

            // Traitement des membres
            $membersData = $request->request->all('members');
            if (is_array($membersData)) {
                foreach ($membersData as $memberData) {
                    if (!empty($memberData['name'])) {
                        $member = new ProjectMember();
                        $member->setProject($project)
                              ->setName($memberData['name'])
                              ->setEmail($memberData['email'] ?? null)
                              ->setRole($memberData['role'] ?? null);
                        
                        $project->addMember($member);
                    }
                }
            }

            // Traitement des liens
            $linksData = $request->request->all('links');
            if (is_array($linksData)) {
                foreach ($linksData as $linkData) {
                    if (!empty($linkData['title']) && !empty($linkData['url'])) {
                        $link = new ProjectLink();
                        $link->setProject($project)
                             ->setTitle($linkData['title'])
                             ->setUrl($linkData['url'])
                             ->setType($linkData['type'] ?? null);
                        
                        $project->addLink($link);
                    }
                }
            }

            // Traitement des fichiers uploadés
            $uploadedFiles = $request->files->get('files', []);
            if (!empty($uploadedFiles)) {
                $this->handleFileUploads($project, $uploadedFiles);
            }

            try {
                $this->entityManager->persist($project);
                $this->entityManager->flush();

                $this->addFlash('success', 'Projet créé avec succès.');
                return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
            }
        }

        return $this->render('admin/project/new.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
            'currentLocale' => $currentLocale
        ]);
    }

    #[Route('/{id}', name: 'admin_project_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Project $project, Request $request): Response
    {
        $locale = $request->query->get('locale', $request->getLocale() ?: 'fr');
        $project = $this->projectRepository->findOneWithAllRelations($project->getId());

        // Vérifier si des traductions existent pour chaque langue
        $availableTranslations = [];
        $supportedLocales = ['fr', 'en'];
        
        foreach ($supportedLocales as $supportedLocale) {
            $translation = $project->getTranslation($supportedLocale);
            $availableTranslations[$supportedLocale] = [
                'exists' => $translation !== null && !empty($translation->getName()),
                'translation' => $translation
            ];
        }

        return $this->render('admin/project/show.html.twig', [
            'project' => $project,
            'currentLocale' => $locale,
            'availableTranslations' => $availableTranslations,
            'supportedLocales' => $supportedLocales
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_project_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Project $project): Response
    {
        $currentLocale = $request->get('locale', $request->getLocale() ?: 'fr');
        $project = $this->projectRepository->findOneWithAllRelations($project->getId());
        
        $form = $this->createForm(ProjectType::class, $project, [
            'current_locale' => $currentLocale
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier l'unicité du code (exclure le projet actuel)
            if (!$this->projectRepository->isCodeUnique($project->getCode(), $project->getId())) {
                $this->addFlash('error', 'Ce code projet existe déjà.');
                return $this->render('admin/project/edit.html.twig', [
                    'project' => $project,
                    'form' => $form->createView(),
                    'currentLocale' => $currentLocale
                ]);
            }

            // Traitement de la traduction pour la langue courante
            $translationData = $request->request->all('translation');
            if (!empty($translationData['name'])) {
                $existingTranslation = $project->getTranslation($currentLocale);
                
                if ($existingTranslation) {
                    $existingTranslation->setName($translationData['name'])
                                      ->setDescription($translationData['description'] ?? '');
                } else {
                    $translation = new ProjectTranslation();
                    $translation->setProject($project)
                              ->setLocale($currentLocale)
                              ->setName($translationData['name'])
                              ->setDescription($translationData['description'] ?? '');
                    
                    $project->addTranslation($translation);
                }
            }

            // Réinjecter les membres
            foreach ($project->getMembers() as $member) {
                $project->removeMember($member);
                $this->entityManager->remove($member);
            }
            
            $membersData = $request->request->all('members');
            if (is_array($membersData)) {
                foreach ($membersData as $memberData) {
                    if (!empty($memberData['name'])) {
                        $member = new ProjectMember();
                        $member->setProject($project)
                              ->setName($memberData['name'])
                              ->setEmail($memberData['email'] ?? null)
                              ->setRole($memberData['role'] ?? null);
                        
                        $project->addMember($member);
                    }
                }
            }

            // Réinjecter les liens
            foreach ($project->getLinks() as $link) {
                $project->removeLink($link);
                $this->entityManager->remove($link);
            }
            
            $linksData = $request->request->all('links');
            if (is_array($linksData)) {
                foreach ($linksData as $linkData) {
                    if (!empty($linkData['title']) && !empty($linkData['url'])) {
                        $link = new ProjectLink();
                        $link->setProject($project)
                             ->setTitle($linkData['title'])
                             ->setUrl($linkData['url'])
                             ->setType($linkData['type'] ?? null);
                        
                        $project->addLink($link);
                    }
                }
            }

            // Traiter les nouveaux fichiers
            $uploadedFiles = $request->files->get('files', []);
            if (!empty($uploadedFiles)) {
                $this->handleFileUploads($project, $uploadedFiles);
            }

            // Traiter les fichiers supprimés
            $removedAttachments = $request->request->get('removed_attachments');
            if ($removedAttachments) {
                $removedIds = json_decode($removedAttachments, true);
                if (is_array($removedIds)) {
                    foreach ($removedIds as $attachmentId) {
                        $attachment = $this->entityManager->getRepository(ProjectAttachment::class)->find($attachmentId);
                        if ($attachment && $attachment->getProject() === $project) {
                            $project->removeAttachment($attachment);
                            $this->entityManager->remove($attachment);
                        }
                    }
                }
            }

            try {
                $this->entityManager->flush();
                $this->addFlash('success', 'Projet modifié avec succès.');

                return $this->redirectToRoute('admin_project_show', ['id' => $project->getId()]);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
            }
        }

        return $this->render('admin/project/edit.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
            'currentLocale' => $currentLocale
        ]);
    }

    #[Route('/save-translation', name: 'admin_project_save_translation', methods: ['POST'])]
    public function saveTranslation(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $projectId = $data['project_id'] ?? null;
            $locale = $data['locale'] ?? null;
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';

            if (!$projectId || !$locale) {
                return new JsonResponse(['success' => false, 'message' => 'Paramètres manquants'], 400);
            }

            $project = $this->projectRepository->find($projectId);
            if (!$project) {
                return new JsonResponse(['success' => false, 'message' => 'Projet non trouvé'], 404);
            }

            // Chercher ou créer la traduction
            $translation = $project->getTranslation($locale);
            if (!$translation) {
                $translation = new ProjectTranslation();
                $translation->setProject($project)->setLocale($locale);
                $project->addTranslation($translation);
            }

            $translation->setName($name)->setDescription($description);

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Traduction sauvegardée avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/get-translation', name: 'admin_project_get_translation', methods: ['GET'])]
    public function getTranslation(Request $request): JsonResponse
    {
        $projectId = $request->query->get('project_id');
        $locale = $request->query->get('locale');

        if (!$projectId || !$locale) {
            return new JsonResponse(['error' => 'Paramètres manquants'], 400);
        }

        $project = $this->projectRepository->findOneWithAllRelations($projectId);
        if (!$project) {
            return new JsonResponse(['error' => 'Projet non trouvé'], 404);
        }

        $translation = $project->getTranslation($locale);

        return new JsonResponse([
            'name' => $translation ? $translation->getName() : '',
            'description' => $translation ? $translation->getDescription() : '',
            'exists' => $translation !== null && !empty($translation->getName()),
            'members' => $project->getMembers()->map(fn($m) => [
                'name' => $m->getName(),
                'email' => $m->getEmail(),
                'role' => $m->getRole()
            ])->toArray(),
            'links' => $project->getLinks()->map(fn($l) => [
                'title' => $l->getTitle(),
                'url' => $l->getUrl(),
                'type' => $l->getType()
            ])->toArray()
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_project_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Project $project): Response
    {
        if ($this->isCsrfTokenValid('delete'.$project->getId(), $request->request->get('_token'))) {
            // Soft delete : désactiver au lieu de supprimer
            $project->setIsActive(false);
            $this->entityManager->flush();

            $this->addFlash('success', 'Projet supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_project_index');
    }

    #[Route('/bulk-delete', name: 'admin_project_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->request->get('ids', []);
        
        if (empty($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun projet sélectionné']);
        }

        try {
            $count = $this->projectRepository->softDeleteByIds($ids);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true, 
                'message' => sprintf('%d projet(s) supprimé(s) avec succès', $count)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
            ]);
        }
    }

    #[Route('/export/excel', name: 'admin_project_export_excel', methods: ['POST'])]
    public function exportExcel(Request $request): Response
    {
        $ids = $request->request->get('ids', []);
        $locale = $request->getLocale() ?: 'fr';
        
        $projects = $this->projectRepository->findForExport($ids, $locale);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Code', 'Nom', 'Catégorie', 'Priorité', 'Statut', 'Responsable', 'Département', 'Date début', 'Date fin', 'Budget', 'Créé le'];
        $sheet->fromArray($headers, null, 'A1');

        // Data
        $row = 2;
        foreach ($projects as $project) {
            $data = [
                $project->getCode(),
                $project->getName($locale) ?: 'N/A',
                $project->getCategoryLabel(),
                $project->getPriorityLabel(),
                $project->getStatusLabel(),
                $project->getResponsible() ?: 'N/A',
                $project->getDepartment() ?: 'N/A',
                $project->getStartDate() ? $project->getStartDate()->format('d/m/Y') : 'N/A',
                $project->getEndDate() ? $project->getEndDate()->format('d/m/Y') : 'N/A',
                $project->getBudget() ? $project->getBudget() . '€' : 'N/A',
                $project->getCreatedAt()->format('d/m/Y H:i')
            ];
            $sheet->fromArray($data, null, 'A' . $row);
            $row++;
        }

        // Style headers
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:K1')->getFill()->getStartColor()->setARGB('FFE0E0E0');

        // Auto-size columns
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'projets_' . date('Y-m-d_H-i-s') . '.xlsx';

        $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');

        ob_start();
        $writer->save('php://output');
        $response->setContent(ob_get_clean());

        return $response;
    }

    #[Route('/export/pdf', name: 'admin_project_export_pdf', methods: ['POST'])]
    public function exportPdf(Request $request): Response
    {
        $ids = $request->request->get('ids', []);
        $locale = $request->getLocale() ?: 'fr';
        
        $projects = $this->projectRepository->findForExport($ids, $locale);

        $html = $this->renderView('admin/project/export_pdf.html.twig', [
            'projects' => $projects,
            'currentLocale' => $locale,
            'exportDate' => new \DateTime()
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'projets_' . date('Y-m-d_H-i-s') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    private function handleFileUploads(Project $project, array $files): void
    {
        $uploadDir = $this->getParameter('projects_directory');
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                try {
                    $file->move($uploadDir, $newFilename);

                    $attachment = new ProjectAttachment();
                    $attachment->setProject($project)
                             ->setFileName($newFilename)
                             ->setOriginalName($file->getClientOriginalName())
                             ->setMimeType($file->getMimeType())
                             ->setFileSize($file->getSize());

                    $project->addAttachment($attachment);

                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier : ' . $file->getClientOriginalName());
                }
            }
        }
    }
}