<?php

namespace App\Controller\Admin;

use App\Entity\Procedure;
use App\Form\ProcedureType;
use App\Repository\ProcedureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/procedure')]
class ProcedureController extends AbstractController
{
    #[Route('/', name: 'admin_procedure_index', methods: ['GET'])]
    public function index(procedureRepository $procedureRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $procedures = $procedureRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/procedure/index.html.twig', [
            'procedures' => $procedures,
        ]);
    }

    #[Route('/new', name: 'admin_procedure_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $procedure = new procedure();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(procedureType::class, $procedure, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gestion de l'upload d'image avec optimisation mémoire
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('imageFile');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $imageFile->move($uploadsDirectory, $newFilename);
                        $procedure->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_procedure_new');
                    }
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($procedure);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été créé avec succès.');
                return $this->redirectToRoute('admin_procedure_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_procedure_new');
            }
        }

        return $this->render('admin/procedure/new.html.twig', [
            'procedure' => $procedure,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_procedure_show', methods: ['GET'])]
    public function show(procedure $procedure): Response
    {
        return $this->render('admin/procedure/show.html.twig', [
            'procedure' => $procedure,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_procedure_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, procedure $procedure, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(procedureType::class, $procedure, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gestion de l'upload d'image avec optimisation mémoire
                $imageFile = $form->get('imageFile')->getData();  
                         

                if ($imageFile) {
                    // Supprimer l'ancienne image si elle existe
                    if ($procedure->getImage()) {
                        $oldImagePath = $this->getParameter('projects_directory').'/'.$procedure->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('projects_directory');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $imageFile->move($uploadsDirectory, $newFilename);
                        $procedure->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_procedure_edit', ['id' => $procedure->getId()]);
                    }
                }

                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été modifié avec succès.');
                return $this->redirectToRoute('admin_procedure_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_procedure_edit', ['id' => $procedure->getId()]);
            }
        }

        return $this->render('admin/procedure/edit.html.twig', [
            'procedure' => $procedure,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_procedure_delete', methods: ['POST'])]
    public function delete(Request $request, procedure $procedure, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$procedure->getId(), $request->request->get('_token'))) {
            try {
                // Supprimer l'image si elle existe
                if ($procedure->getImage()) {
                    $imagePath = $this->getParameter('procedures_directory').'/'.$procedure->getImage();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $entityManager->remove($procedure);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_procedure_index', [], Response::HTTP_SEE_OTHER);
    }
}