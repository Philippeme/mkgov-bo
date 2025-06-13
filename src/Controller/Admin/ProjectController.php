<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/project')]
class ProjectController extends AbstractController
{
    #[Route('/', name: 'admin_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $projects = $projectRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/new', name: 'admin_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $project = new Project();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(ProjectType::class, $project, [
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
                        $uploadsDirectory = $this->getParameter('projects_directory');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $imageFile->move($uploadsDirectory, $newFilename);
                        $project->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_project_new');
                    }
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($project);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été créé avec succès.');
                return $this->redirectToRoute('admin_project_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_project_new');
            }
        }

        return $this->render('admin/project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        return $this->render('admin/project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(ProjectType::class, $project, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Gestion de l'upload d'image avec optimisation mémoire
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    // Supprimer l'ancienne image si elle existe
                    if ($project->getImage()) {
                        $oldImagePath = $this->getParameter('projects_directory').'/'.$project->getImage();
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
                        $project->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_project_edit', ['id' => $project->getId()]);
                    }
                }

                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été modifié avec succès.');
                return $this->redirectToRoute('admin_project_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_project_edit', ['id' => $project->getId()]);
            }
        }

        return $this->render('admin/project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$project->getId(), $request->request->get('_token'))) {
            try {
                // Supprimer l'image si elle existe
                if ($project->getImage()) {
                    $imagePath = $this->getParameter('projects_directory').'/'.$project->getImage();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $entityManager->remove($project);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été supprimé avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_project_index', [], Response::HTTP_SEE_OTHER);
    }
}