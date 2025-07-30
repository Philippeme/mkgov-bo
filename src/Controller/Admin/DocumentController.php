<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\DataTableService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/document')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'admin_document_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/document/index.html.twig');
    }

    #[Route('/datatable', name: 'admin_document_datatable', methods: ['POST', 'GET'])]
    public function datatable(Request $request, DocumentRepository $documentRepository): JsonResponse
    {
        // Récupérer les paramètres DataTables
        $draw = intval($request->get('draw', 1));
        $start = intval($request->get('start', 0));
        $length = intval($request->get('length', 10));
        $search = $request->get('search', []);
        $order = $request->get('order', []);
        $columns = $request->get('columns', []);

        // Ajouter les colonnes au search pour la recherche par colonnes
        if (!empty($columns)) {
            $search['columns'] = $columns;
        }

        try {
            // Obtenir les résultats paginés
            $documents = $documentRepository->getDataTablesResults($search, $order, $start, $length);
            
            // Compter les enregistrements
            $recordsTotal = $documentRepository->countTotal();
            $recordsFiltered = $documentRepository->countFiltered($search);

            // Formatter les données pour DataTables
            $data = [];
            foreach ($documents as $document) {
                $actions = $this->renderView('admin/document/_actions.html.twig', [
                    'document' => $document
                ]);

                $status = $document->isActive() 
                    ? '<span class="badge bg-success">Actif</span>' 
                    : '<span class="badge bg-danger">Inactif</span>';

                $fileInfo = '';
                if ($document->getFile()) {
                    $fileInfo = sprintf(
                        '<div class="file-info">
                            <div class="file-name">%s</div>
                            <div class="file-meta text-muted small">%s • %s</div>
                        </div>',
                        htmlspecialchars($document->getOriginalFilename() ?? 'Fichier'),
                        $document->getFormattedFileSize(),
                        strtoupper($document->getFileExtension() ?? 'unknown')
                    );
                }

                $data[] = [
                    'id' => $document->getId(),
                    'nom' => htmlspecialchars($document->getNom()),
                    'description' => htmlspecialchars(substr($document->getDescription(), 0, 100) . (strlen($document->getDescription()) > 100 ? '...' : '')),
                    'file' => $fileInfo,
                    'fileSize' => $document->getFormattedFileSize(),
                    'createdAt' => $document->getCreatedAt()->format('d/m/Y H:i'),
                    'status' => $status,
                    'actions' => $actions
                ];
            }

            return new JsonResponse([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du chargement des données: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gestion de l'upload de fichier
                $uploadedFile = $form->get('uploadedFile')->getData();
                if ($uploadedFile) {
                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

                    $uploadsDirectory = $this->getParameter('documents_directory');
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0755, true);
                    }
                    
                    $uploadedFile->move($uploadsDirectory, $newFilename);
                    
                    // Mettre à jour les propriétés du document
                    $document->setFile($newFilename);
                    $document->setOriginalFilename($uploadedFile->getClientOriginalName());
                    $document->setMimeType($uploadedFile->getMimeType());
                    $document->setFileSize($uploadedFile->getSize());
                }

                $entityManager->persist($document);
                $entityManager->flush();

                $this->addFlash('success', 'Le document a été créé avec succès.');
                return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du document: ' . $e->getMessage());
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
                // Gestion de la suppression du fichier existant
                $removeFile = $form->get('removeFile')->getData();
                if ($removeFile && $document->getFile()) {
                    $oldFilePath = $this->getParameter('documents_directory').'/'.$document->getFile();
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                    $document->setFile(null);
                    $document->setOriginalFilename(null);
                    $document->setMimeType(null);
                    $document->setFileSize(null);
                }

                // Gestion de l'upload du nouveau fichier
                $uploadedFile = $form->get('uploadedFile')->getData();
                if ($uploadedFile) {
                    // Supprimer l'ancien fichier s'il existe
                    if ($document->getFile()) {
                        $oldFilePath = $this->getParameter('documents_directory').'/'.$document->getFile();
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }

                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

                    $uploadsDirectory = $this->getParameter('documents_directory');
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0755, true);
                    }
                    
                    $uploadedFile->move($uploadsDirectory, $newFilename);
                    
                    $document->setFile($newFilename);
                    $document->setOriginalFilename($uploadedFile->getClientOriginalName());
                    $document->setMimeType($uploadedFile->getMimeType());
                    $document->setFileSize($uploadedFile->getSize());
                }

                $entityManager->flush();

                $this->addFlash('success', 'Le document a été modifié avec succès.');
                return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du document: ' . $e->getMessage());
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
                // Soft delete - marquer comme inactif au lieu de supprimer
                $document->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Le document a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du document: ' . $e->getMessage());
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
                'message' => 'Statut mis à jour avec succès.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/download', name: 'admin_document_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        if (!$document->getFile()) {
            throw $this->createNotFoundException('Aucun fichier associé à ce document.');
        }

        $filePath = $this->getParameter('documents_directory').'/'.$document->getFile();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier n\'existe pas.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getOriginalFilename() ?? $document->getFile()
        );

        return $response;
    }

    #[Route('/{id}/preview', name: 'admin_document_preview', methods: ['GET'])]
    public function preview(Document $document): Response
    {
        if (!$document->getFile()) {
            throw $this->createNotFoundException('Aucun fichier associé à ce document.');
        }

        $filePath = $this->getParameter('documents_directory').'/'.$document->getFile();
        
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier n\'existe pas.');
        }

        // Pour les images, on peut les afficher directement
        if ($document->isImage()) {
            $response = new BinaryFileResponse($filePath);
            $response->headers->set('Content-Type', $document->getMimeType());
            return $response;
        }

        // Pour les PDFs, on peut essayer de les afficher dans le navigateur
        if ($document->isPdf()) {
            $response = new BinaryFileResponse($filePath);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
            return $response;
        }

        // Pour les autres types, rediriger vers le téléchargement
        return $this->redirectToRoute('admin_document_download', ['id' => $document->getId()]);
    }

    #[Route('/bulk-action', name: 'admin_document_bulk', methods: ['POST'])]
    public function bulkAction(Request $request, EntityManagerInterface $entityManager, DocumentRepository $documentRepository): JsonResponse
    {
        $action = $request->get('action');
        $ids = $request->get('ids', []);

        if (empty($ids) || !is_array($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun élément sélectionné.'], 400);
        }

        try {
            $documents = $documentRepository->findBy(['id' => $ids]);
            $count = 0;

            foreach ($documents as $document) {
                switch ($action) {
                    case 'activate':
                        $document->setIsActive(true);
                        $count++;
                        break;
                    case 'deactivate':
                        $document->setIsActive(false);
                        $count++;
                        break;
                    case 'delete':
                        $document->setIsActive(false); // Soft delete
                        $count++;
                        break;
                }
            }

            $entityManager->flush();

            $actionNames = [
                'activate' => 'activés',
                'deactivate' => 'désactivés',
                'delete' => 'supprimés'
            ];

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('%d document(s) %s avec succès.', $count, $actionNames[$action] ?? 'traités')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de l\'action groupée: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/stats', name: 'admin_document_stats', methods: ['GET'])]
    public function stats(DocumentRepository $documentRepository): JsonResponse
    {
        try {
            $stats = $documentRepository->getStorageStats();
            return new JsonResponse($stats);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}