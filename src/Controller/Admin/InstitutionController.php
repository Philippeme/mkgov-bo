<?php

namespace App\Controller\Admin;

use App\Entity\Institution;
use App\Form\InstitutionType;
use App\Repository\InstitutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/institution')]
class InstitutionController extends AbstractController
{
    #[Route('/', name: 'admin_institution_index', methods: ['GET'])]
    public function index(institutionRepository $institutionRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $institutions = $institutionRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/institution/index.html.twig', [
            'institutions' => $institutions,
        ]);
    }

    #[Route('/new', name: 'admin_institution_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $institution = new institution();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(institutionType::class, $institution, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($institution);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été créé avec succès.');
                return $this->redirectToRoute('admin_institution_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_institution_new');
            }
        }

        return $this->render('admin/institution/new.html.twig', [
            'institution' => $institution,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_institution_show', methods: ['GET'])]
    public function show(institution $institution): Response
    {
        return $this->render('admin/institution/show.html.twig', [
            'institution' => $institution,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_institution_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, institution $institution, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(institutionType::class, $institution, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Institution has been modified successfully.');
                return $this->redirectToRoute('admin_institution_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_institution_edit', ['id' => $institution>getId()]);
            }
        }

        return $this->render('admin/institution/edit.html.twig', [
            'institution' => $institution,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_institution_delete', methods: ['POST'])]
    public function delete(Request $request, institution $institution, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$institution->getId(), $request->request->get('_token'))) {
            try {

                $entityManager->remove($institution);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Institution has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_institution_index', [], Response::HTTP_SEE_OTHER);
    }
}