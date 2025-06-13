<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/document')]
class DocumentController extends AbstractController
{
    #[Route('/', name: 'admin_document_index', methods: ['GET'])]
    public function index(documentRepository $documentRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $documents = $documentRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/document/index.html.twig', [
            'documents' => $documents,
        ]);
    }

    #[Route('/new', name: 'admin_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $document = new document();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(documentType::class, $document, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                $pdfFile = $form->get('pdfFile')->getData();
                if ($pdfFile) {
                    $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$pdfFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('pdf_uploads'); // Assurez-vous que le paramètre correspond bien au dossier des fichiers PDF
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }

                        // Vérifier que l'extension est bien un PDF
                        if ($pdfFile->guessExtension() !== 'pdf') {
                            throw new FileException('Le fichier doit être un PDF.');
                        }

                        $pdfFile->move($uploadsDirectory, $newFilename);
                        $document->setPdf($newFilename); // Stocker le nom du fichier PDF dans l'entité
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement du fichier PDF : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_document_new');
                    }
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($document);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'The document has been created successfully.');
                return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_document_new');
            }
        }

        return $this->render('admin/document/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_document_show', methods: ['GET'])]
    public function show(document $document): Response
    {
        return $this->render('admin/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_document_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, document $document, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(documentType::class, $document, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                $pdfFile = $form->get('pdfFile')->getData();  

                if ($pdfFile) {
                    // Supprimer l'ancien fichier PDF si existant
                    if ($document->getPdf()) {
                        $oldPdfPath = $this->getParameter('pdf_uploads').'/'.$document->getPdf();
                        if (file_exists($oldPdfPath)) {
                            unlink($oldPdfPath);
                        }
                    }

                    $originalFilename = pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$pdfFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('pdf_uploads');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }

                        // Vérifier que l'extension est bien un PDF
                        if ($pdfFile->guessExtension() !== 'pdf') {
                            throw new FileException('Le fichier doit être un PDF.');
                        }

                        $pdfFile->move($uploadsDirectory, $newFilename);
                        $document->setPdf($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement du fichier PDF : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_document_edit', ['id' => $document->getId()]);
                    }
                }
                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Family has been modified successfully.');
                return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_document_edit', ['id' => $document->getId()]);
            }
        }

        return $this->render('admin/document/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_document_delete', methods: ['POST'])]
    public function delete(Request $request, document $document, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            try {

                // Supprimer le fichier PDF si existant
                if ($document->getPdf()) {
                    $pdfPath = $this->getParameter('pdf_uploads').'/'.$document->getPdf();
                    if (file_exists($pdfPath)) {
                        unlink($pdfPath);
                    }
                }

                $entityManager->remove($document);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Document has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_document_index', [], Response::HTTP_SEE_OTHER);
    }
}