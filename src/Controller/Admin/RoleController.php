<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Form\RoleType;
use App\Repository\RoleRepository;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/role')]
class RoleController extends AbstractController
{
    #[Route('/', name: 'admin_role_index', methods: ['GET'])]
    public function index(Request $request, RoleRepository $roleRepository): Response
    {
        // Récupération des paramètres de filtrage depuis la requête
        $filters = [
            'search' => $request->query->get('search'),
            'active' => $request->query->get('active') !== null ? (bool) $request->query->get('active') : null,
            'system' => $request->query->get('system') !== null ? (bool) $request->query->get('system') : null,
        ];

        // Suppression des filtres vides pour optimiser les requêtes
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        // Optimisation : récupération avec limite pour éviter les problèmes de mémoire
        $roles = $roleRepository->findRolesWithFilters($filters);
        $statistics = $roleRepository->countRolesByStatus();
        $mostUsed = $roleRepository->findMostUsedRoles(5);

        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles,
            'filters' => $filters,
            'statistics' => $statistics,
            'most_used' => $mostUsed,
        ]);
    }

    #[Route('/new', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, PermissionRepository $permissionRepository): Response
    {
        $role = new Role();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(RoleType::class, $role, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérification de l'unicité du nom de rôle
                if ($roleRepository->existsByName($role->getName())) {
                    $this->addFlash('error', 'A role with this name already exists.');
                    return $this->render('admin/role/new.html.twig', [
                        'role' => $role,
                        'form' => $form,
                    ]);
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($role);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Role has been created successfully.');
                return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating role: ' . $e->getMessage());
                return $this->redirectToRoute('admin_role_new');
            }
        }

        // Récupération des permissions groupées par catégorie pour l'affichage
        $permissionsByCategory = $permissionRepository->findPermissionsByCategory();

        return $this->render('admin/role/new.html.twig', [
            'role' => $role,
            'form' => $form,
            'permissions_by_category' => $permissionsByCategory,
        ]);
    }

    #[Route('/{id}', name: 'admin_role_show', methods: ['GET'])]
    public function show(Role $role): Response
    {
        return $this->render('admin/role/show.html.twig', [
            'role' => $role,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_role_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Role $role, EntityManagerInterface $entityManager, RoleRepository $roleRepository, PermissionRepository $permissionRepository): Response
    {
        // Vérification que le rôle système ne peut pas être modifié
        if ($role->isSystem()) {
            $this->addFlash('warning', 'System roles cannot be modified.');
            return $this->redirectToRoute('admin_role_show', ['id' => $role->getId()]);
        }

        // Configuration optimisée du formulaire
        $form = $this->createForm(RoleType::class, $role, [
            'validation_groups' => ['Default'],
            'is_edit' => true,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérification de l'unicité du nom de rôle (excluant le rôle actuel)
                if ($roleRepository->existsByName($role->getName(), $role->getId())) {
                    $this->addFlash('error', 'A role with this name already exists.');
                    return $this->render('admin/role/edit.html.twig', [
                        'role' => $role,
                        'form' => $form,
                        'permissions_by_category' => $permissionRepository->findPermissionsByCategory(),
                    ]);
                }

                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Role has been updated successfully.');
                return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating role: ' . $e->getMessage());
                return $this->redirectToRoute('admin_role_edit', ['id' => $role->getId()]);
            }
        }

        // Récupération des permissions groupées par catégorie pour l'affichage
        $permissionsByCategory = $permissionRepository->findPermissionsByCategory();

        return $this->render('admin/role/edit.html.twig', [
            'role' => $role,
            'form' => $form,
            'permissions_by_category' => $permissionsByCategory,
        ]);
    }

    #[Route('/{id}', name: 'admin_role_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        // Vérification que le rôle système ne peut pas être supprimé
        if ($role->isSystem()) {
            $this->addFlash('error', 'System roles cannot be deleted.');
            return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
        }

        // Vérification que le rôle n'est pas assigné à des utilisateurs
        if ($role->getUsersCount() > 0) {
            $this->addFlash('error', 'Cannot delete role that is assigned to users. Please remove users from this role first.');
            return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$role->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($role);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Role has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting role: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_role_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        // Vérification que le rôle système ne peut pas être désactivé
        if ($role->isSystem()) {
            $this->addFlash('error', 'System roles cannot be deactivated.');
            return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('toggle_status'.$role->getId(), $request->request->get('_token'))) {
            try {
                $role->setIsActive(!$role->isActive());
                $entityManager->flush();

                $status = $role->isActive() ? 'activated' : 'deactivated';
                $this->addFlash('success', "Role has been {$status} successfully.");
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating role status: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/clone', name: 'admin_role_clone', methods: ['POST'])]
    public function clone(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('clone'.$role->getId(), $request->request->get('_token'))) {
            try {
                // Création d'un nouveau rôle basé sur l'existant
                $clonedRole = new Role();
                $clonedRole->setName($role->getName() . '_COPY_' . uniqid());
                $clonedRole->setLabel($role->getLabel() . ' (Copy)');
                $clonedRole->setDescription($role->getDescription());
                $clonedRole->setBadgeColor($role->getBadgeColor());
                $clonedRole->setIsActive(false); // Désactivé par défaut
                $clonedRole->setIsSystem(false); // Jamais système
                $clonedRole->setDisplayOrder($role->getDisplayOrder() + 1);

                // Copie des permissions
                foreach ($role->getPermissions() as $permission) {
                    $clonedRole->addPermission($permission);
                }

                $entityManager->persist($clonedRole);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Role has been cloned successfully. You can now edit the copy.');
                return $this->redirectToRoute('admin_role_edit', ['id' => $clonedRole->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error cloning role: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bulk-action', name: 'admin_role_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, EntityManagerInterface $entityManager, RoleRepository $roleRepository): Response
    {
        $action = $request->request->get('action');
        $roleIds = $request->request->all('roles');

        if (!$action || empty($roleIds)) {
            $this->addFlash('error', 'Please select roles and an action.');
            return $this->redirectToRoute('admin_role_index');
        }

        try {
            $roles = $roleRepository->findBy(['id' => $roleIds]);
            $count = 0;

            foreach ($roles as $role) {
                // Protection des rôles système
                if ($role->isSystem() && in_array($action, ['deactivate', 'delete'])) {
                    continue;
                }

                switch ($action) {
                    case 'activate':
                        $role->setIsActive(true);
                        $count++;
                        break;
                    case 'deactivate':
                        $role->setIsActive(false);
                        $count++;
                        break;
                    case 'delete':
                        // Vérification que le rôle n'est pas assigné à des utilisateurs
                        if ($role->getUsersCount() === 0) {
                            $entityManager->remove($role);
                            $count++;
                        }
                        break;
                }
            }

            $entityManager->flush();
            $entityManager->clear();

            $this->addFlash('success', "Bulk action '{$action}' applied to {$count} roles successfully.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error performing bulk action: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/permissions', name: 'admin_role_permissions', methods: ['GET', 'POST'])]
    public function managePermissions(Request $request, Role $role, EntityManagerInterface $entityManager, PermissionRepository $permissionRepository): Response
    {
        if ($request->isMethod('POST')) {
            $selectedPermissions = $request->request->all('permissions');
            
            try {
                // Suppression de toutes les permissions existantes
                foreach ($role->getPermissions() as $permission) {
                    $role->removePermission($permission);
                }

                // Ajout des nouvelles permissions sélectionnées
                if (!empty($selectedPermissions)) {
                    $permissions = $permissionRepository->findBy(['id' => $selectedPermissions]);
                    foreach ($permissions as $permission) {
                        $role->addPermission($permission);
                    }
                }

                $entityManager->flush();
                
                $this->addFlash('success', 'Role permissions have been updated successfully.');
                return $this->redirectToRoute('admin_role_show', ['id' => $role->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating role permissions: ' . $e->getMessage());
            }
        }

        $permissionsByCategory = $permissionRepository->findPermissionsByCategory();
        $rolePermissionIds = $role->getPermissions()->map(fn($p) => $p->getId())->toArray();

        return $this->render('admin/role/permissions.html.twig', [
            'role' => $role,
            'permissions_by_category' => $permissionsByCategory,
            'role_permission_ids' => $rolePermissionIds,
        ]);
    }
}