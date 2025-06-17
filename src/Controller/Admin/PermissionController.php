<?php

namespace App\Controller\Admin;

use App\Entity\Permission;
use App\Form\PermissionType;
use App\Repository\PermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/permission')]
class PermissionController extends AbstractController
{
    #[Route('/', name: 'admin_permission_index', methods: ['GET'])]
    public function index(Request $request, PermissionRepository $permissionRepository): Response
    {
        // Récupération des paramètres de filtrage depuis la requête
        $filters = [
            'search' => $request->query->get('search'),
            'category' => $request->query->get('category'),
            'action' => $request->query->get('action'),
            'resource' => $request->query->get('resource'),
            'active' => $request->query->get('active') !== null ? (bool) $request->query->get('active') : null,
            'system' => $request->query->get('system') !== null ? (bool) $request->query->get('system') : null,
        ];

        // Suppression des filtres vides pour optimiser les requêtes
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        // Optimisation : récupération avec limite pour éviter les problèmes de mémoire
        $permissions = $permissionRepository->findPermissionsWithFilters($filters);
        $permissionsByCategory = $permissionRepository->findPermissionsByCategory();
        $categories = $permissionRepository->findUniqueCategories();
        $actions = $permissionRepository->findUniqueActions();
        $resources = $permissionRepository->findUniqueResources();
        $statistics = $permissionRepository->countPermissionsByStatus();
        $categoriesStats = $permissionRepository->countPermissionsByCategory();

        return $this->render('admin/permission/index.html.twig', [
            'permissions' => $permissions,
            'permissions_by_category' => $permissionsByCategory,
            'categories' => $categories,
            'actions' => $actions,
            'resources' => $resources,
            'filters' => $filters,
            'statistics' => $statistics,
            'categories_stats' => $categoriesStats,
        ]);
    }

    #[Route('/new', name: 'admin_permission_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, PermissionRepository $permissionRepository): Response
    {
        $permission = new Permission();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(PermissionType::class, $permission, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérification de l'unicité du nom de permission
                if ($permissionRepository->existsByName($permission->getName())) {
                    $this->addFlash('error', 'A permission with this name already exists.');
                    return $this->render('admin/permission/new.html.twig', [
                        'permission' => $permission,
                        'form' => $form,
                    ]);
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($permission);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Permission has been created successfully.');
                return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating permission: ' . $e->getMessage());
                return $this->redirectToRoute('admin_permission_new');
            }
        }

        return $this->render('admin/permission/new.html.twig', [
            'permission' => $permission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_permission_show', methods: ['GET'])]
    public function show(Permission $permission): Response
    {
        return $this->render('admin/permission/show.html.twig', [
            'permission' => $permission,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_permission_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Permission $permission, EntityManagerInterface $entityManager, PermissionRepository $permissionRepository): Response
    {
        // Vérification que la permission système ne peut pas être modifiée
        if ($permission->isSystem()) {
            $this->addFlash('warning', 'System permissions cannot be modified.');
            return $this->redirectToRoute('admin_permission_show', ['id' => $permission->getId()]);
        }

        // Configuration optimisée du formulaire
        $form = $this->createForm(PermissionType::class, $permission, [
            'validation_groups' => ['Default'],
            'is_edit' => true,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Vérification de l'unicité du nom de permission (excluant la permission actuelle)
                if ($permissionRepository->existsByName($permission->getName(), $permission->getId())) {
                    $this->addFlash('error', 'A permission with this name already exists.');
                    return $this->render('admin/permission/edit.html.twig', [
                        'permission' => $permission,
                        'form' => $form,
                    ]);
                }

                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Permission has been updated successfully.');
                return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating permission: ' . $e->getMessage());
                return $this->redirectToRoute('admin_permission_edit', ['id' => $permission->getId()]);
            }
        }

        return $this->render('admin/permission/edit.html.twig', [
            'permission' => $permission,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_permission_delete', methods: ['POST'])]
    public function delete(Request $request, Permission $permission, EntityManagerInterface $entityManager): Response
    {
        // Vérification que la permission système ne peut pas être supprimée
        if ($permission->isSystem()) {
            $this->addFlash('error', 'System permissions cannot be deleted.');
            return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
        }

        // Vérification que la permission n'est pas assignée à des rôles
        if ($permission->getRolesCount() > 0) {
            $this->addFlash('error', 'Cannot delete permission that is assigned to roles. Please remove this permission from all roles first.');
            return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$permission->getId(), $request->request->get('_token'))) {
            try {
                $entityManager->remove($permission);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Permission has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting permission: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_permission_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Permission $permission, EntityManagerInterface $entityManager): Response
    {
        // Vérification que la permission système ne peut pas être désactivée
        if ($permission->isSystem()) {
            $this->addFlash('error', 'System permissions cannot be deactivated.');
            return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('toggle_status'.$permission->getId(), $request->request->get('_token'))) {
            try {
                $permission->setIsActive(!$permission->isActive());
                $entityManager->flush();

                $status = $permission->isActive() ? 'activated' : 'deactivated';
                $this->addFlash('success', "Permission has been {$status} successfully.");
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating permission status: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bulk-action', name: 'admin_permission_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, EntityManagerInterface $entityManager, PermissionRepository $permissionRepository): Response
    {
        $action = $request->request->get('action');
        $permissionIds = $request->request->all('permissions');

        if (!$action || empty($permissionIds)) {
            $this->addFlash('error', 'Please select permissions and an action.');
            return $this->redirectToRoute('admin_permission_index');
        }

        try {
            $permissions = $permissionRepository->findBy(['id' => $permissionIds]);
            $count = 0;

            foreach ($permissions as $permission) {
                // Protection des permissions système
                if ($permission->isSystem() && in_array($action, ['deactivate', 'delete'])) {
                    continue;
                }

                switch ($action) {
                    case 'activate':
                        $permission->setIsActive(true);
                        $count++;
                        break;
                    case 'deactivate':
                        $permission->setIsActive(false);
                        $count++;
                        break;
                    case 'delete':
                        // Vérification que la permission n'est pas assignée à des rôles
                        if ($permission->getRolesCount() === 0) {
                            $entityManager->remove($permission);
                            $count++;
                        }
                        break;
                }
            }

            $entityManager->flush();
            $entityManager->clear();

            $this->addFlash('success', "Bulk action '{$action}' applied to {$count} permissions successfully.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error performing bulk action: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/generate-crud', name: 'admin_permission_generate_crud', methods: ['POST'])]
    public function generateCrudPermissions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $resource = $request->request->get('resource');
        $category = $request->request->get('category', 'System');
        
        if (!$resource) {
            $this->addFlash('error', 'Resource name is required.');
            return $this->redirectToRoute('admin_permission_index');
        }

        try {
            $actions = ['create', 'read', 'update', 'delete'];
            $createdCount = 0;

            foreach ($actions as $action) {
                $permissionName = strtolower($action . '_' . $resource);
                
                // Vérifier si la permission existe déjà
                $existingPermission = $entityManager->getRepository(Permission::class)->findByName($permissionName);
                if ($existingPermission) {
                    continue;
                }

                $permission = new Permission();
                $permission->setName($permissionName);
                $permission->setLabel(ucfirst($action) . ' ' . ucfirst($resource));
                $permission->setDescription('Permission to ' . $action . ' ' . $resource . ' resources');
                $permission->setCategory($category);
                $permission->setAction($action);
                $permission->setResource(strtolower($resource));
                $permission->setIsActive(true);
                $permission->setIsSystem(false);
                $permission->setDisplayOrder($createdCount * 10);

                $entityManager->persist($permission);
                $createdCount++;
            }

            $entityManager->flush();
            $entityManager->clear();

            $this->addFlash('success', "Generated {$createdCount} CRUD permissions for '{$resource}' resource.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error generating CRUD permissions: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_permission_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/export', name: 'admin_permission_export', methods: ['GET'])]
    public function export(PermissionRepository $permissionRepository): Response
    {
        $permissions = $permissionRepository->findActivePermissions();
        
        $csvData = [];
        $csvData[] = ['Name', 'Label', 'Description', 'Category', 'Action', 'Resource', 'Active', 'System'];
        
        foreach ($permissions as $permission) {
            $csvData[] = [
                $permission->getName(),
                $permission->getLabel(),
                $permission->getDescription(),
                $permission->getCategory(),
                $permission->getAction(),
                $permission->getResource(),
                $permission->isActive() ? 'Yes' : 'No',
                $permission->isSystem() ? 'Yes' : 'No',
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="permissions.csv"');

        $output = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $response->setContent($content);
        return $response;
    }

    #[Route('/category/{category}', name: 'admin_permission_by_category', methods: ['GET'])]
    public function byCategory(string $category, PermissionRepository $permissionRepository): Response
    {
        $permissions = $permissionRepository->findPermissionsWithFilters(['category' => $category]);
        
        return $this->render('admin/permission/category.html.twig', [
            'category' => $category,
            'permissions' => $permissions,
        ]);
    }
}