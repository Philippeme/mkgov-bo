<?php

namespace App\Controller\Admin;

use App\Entity\Family;
use App\Form\FamilyType;
use App\Repository\FamilyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/family')]
class FamilyController extends AbstractController
{
    #[Route('/', name: 'admin_family_index', methods: ['GET'])]
    public function index(familyRepository $familyRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $families = $familyRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/family/index.html.twig', [
            'families' => $families,
        ]);
    }

    #[Route('/new', name: 'admin_family_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $family = new family();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(familyType::class, $family, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($family);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été créé avec succès.');
                return $this->redirectToRoute('admin_family_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_family_new');
            }
        }

        return $this->render('admin/family/new.html.twig', [
            'family' => $family,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_family_show', methods: ['GET'])]
    public function show(family $family): Response
    {
        return $this->render('admin/family/show.html.twig', [
            'family' => $family,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_family_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, family $family, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(familyType::class, $family, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Family has been modified successfully.');
                return $this->redirectToRoute('admin_family_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_family_edit', ['id' => $family->getId()]);
            }
        }

        return $this->render('admin/family/edit.html.twig', [
            'family' => $family,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_family_delete', methods: ['POST'])]
    public function delete(Request $request, family $family, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$family->getId(), $request->request->get('_token'))) {
            try {

                $entityManager->remove($family);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Family has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_family_index', [], Response::HTTP_SEE_OTHER);
    }
}