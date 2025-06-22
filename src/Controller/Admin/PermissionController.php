<?php

namespace App\Controller\Admin;

use App\Entity\Permission;
use App\Form\PermissionType;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/permission')]
class PermissionController extends AbstractController
{
    #[Route('/', name: 'admin_permission_index', methods: ['GET'])]
    public function index(permissionRepository $permissionRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $permissions = $permissionRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/permission/index.html.twig', [
            'permissions' => $permissions,
        ]);
    }

    #[Route('/new', name: 'admin_permission_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $permission = new permission();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(permissionType::class, $permission, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($permission);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Permission has been created successfully.');
                return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_permission_new');
            }
        }

        return $this->render('admin/permission/new.html.twig', [
            'permission' => $permission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_permission_show', methods: ['GET'])]
    public function show(permission $permission): Response
    {
        return $this->render('admin/permission/show.html.twig', [
            'permission' => $permission,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_permission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, permission $permission, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(permissionType::class, $permission, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Permission has been modified successfully.');
                return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_permission_edit', ['id' => $permission->getId()]);
            }
        }

        return $this->render('admin/permission/edit.html.twig', [
            'permission' => $permission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_permission_delete', methods: ['POST'])]
    public function delete(Request $request, permission $permission, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$permission->getId(), $request->request->get('_token'))) {
            try {

                $entityManager->remove($permission);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Family has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
    }
}