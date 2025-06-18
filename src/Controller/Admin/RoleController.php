<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Form\RoleType;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/role')]
class RoleController extends AbstractController
{
    #[Route('/', name: 'admin_role_index', methods: ['GET'])]
    public function index(RoleRepository $roleRepository, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        
        $filters = [
            'search' => $request->query->get('search'),
            'system' => $request->query->get('system'),
            'permission' => $request->query->get('permission'),
        ];

        $roles = $roleRepository->findActiveRolesWithFilters($filters, $page, $limit);
        $statistics = $roleRepository->getRoleStatistics();
        $rolesWithUserCount = $roleRepository->findRolesWithUserCount();
        
        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles,
            'statistics' => $statistics,
            'roles_with_user_count' => $rolesWithUserCount,
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/new', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $role = new Role();
        
        $form = $this->createForm(RoleType::class, $role, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($role);
                $entityManager->flush();
                $entityManager->clear();

                $this->addFlash('success', 'Role has been created successfully.');
                return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating role: ' . $e->getMessage());
                return $this->redirectToRoute('admin_role_new');
            }
        }

        return $this->render('admin/role/new.html.twig', [
            'role' => $role,
            'form' => $form,
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
    public function edit(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RoleType::class, $role, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();
                $entityManager->clear();

                $this->addFlash('success', 'Role has been updated successfully.');
                return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating role: ' . $e->getMessage());
                return $this->redirectToRoute('admin_role_edit', ['id' => $role->getId()]);
            }
        }

        return $this->render('admin/role/edit.html.twig', [
            'role' => $role,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_role_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$role->getId(), $request->request->get('_token'))) {
            try {
                // Check if role is system role
                if ($role->isSystem()) {
                    $this->addFlash('error', 'Cannot delete system role.');
                    return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                }

                // Check if role has users
                if ($role->getUsers()->count() > 0) {
                    $this->addFlash('error', 'Cannot delete role that has assigned users.');
                    return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                }

                $entityManager->remove($role);
                $entityManager->flush();
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
        if ($this->isCsrfTokenValid('toggle'.$role->getId(), $request->request->get('_token'))) {
            try {
                // Check if role is system role
                if ($role->isSystem()) {
                    $this->addFlash('error', 'Cannot modify system role status.');
                    return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
                }

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

    #[Route('/{id}/permissions', name: 'admin_role_permissions', methods: ['GET', 'POST'])]
    public function managePermissions(Request $request, Role $role, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('permissions'.$role->getId(), $request->request->get('_token'))) {
                try {
                    $selectedPermissions = $request->request->all('permissions');
                    
                    // Clear existing permissions
                    $role->getPermissions()->clear();
                    
                    // Add selected permissions
                    if (!empty($selectedPermissions)) {
                        $permissionRepository = $entityManager->getRepository(\App\Entity\Permission::class);
                        foreach ($selectedPermissions as $permissionId) {
                            $permission = $permissionRepository->find($permissionId);
                            if ($permission) {
                                $role->addPermission($permission);
                            }
                        }
                    }

                    $entityManager->flush();
                    $this->addFlash('success', 'Role permissions have been updated successfully.');
                    
                    return $this->redirectToRoute('admin_role_show', ['id' => $role->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error updating permissions: ' . $e->getMessage());
                }
            }
        }

        $allPermissions = $entityManager->getRepository(\App\Entity\Permission::class)->findBy([], ['name' => 'ASC']);
        
        return $this->render('admin/role/permissions.html.twig', [
            'role' => $role,
            'all_permissions' => $allPermissions,
        ]);
    }

    #[Route('/system/create-defaults', name: 'admin_role_create_defaults', methods: ['POST'])]
    public function createDefaultRoles(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('create_defaults', $request->request->get('_token'))) {
            try {
                $defaultRoles = [
                    [
                        'name' => 'ROLE_SUPER_ADMIN',
                        'displayName' => 'Super Administrator',
                        'description' => 'Full system access and administration rights',
                        'isSystem' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'name' => 'ROLE_ADMIN',
                        'displayName' => 'Administrator',
                        'description' => 'Administrative access to most features',
                        'isSystem' => true,
                        'displayOrder' => 2,
                    ],
                    [
                        'name' => 'ROLE_MANAGER',
                        'displayName' => 'Manager',
                        'description' => 'Management level access',
                        'isSystem' => true,
                        'displayOrder' => 3,
                    ],
                    [
                        'name' => 'ROLE_USER',
                        'displayName' => 'User',
                        'description' => 'Standard user access',
                        'isSystem' => true,
                        'displayOrder' => 4,
                    ],
                ];

                $created = 0;
                foreach ($defaultRoles as $roleData) {
                    // Check if role already exists
                    $existingRole = $entityManager->getRepository(Role::class)->findByName($roleData['name']);
                    if (!$existingRole) {
                        $role = new Role();
                        $role->setName($roleData['name']);
                        $role->setDisplayName($roleData['displayName']);
                        $role->setDescription($roleData['description']);
                        $role->setIsSystem($roleData['isSystem']);
                        $role->setDisplayOrder($roleData['displayOrder']);
                        
                        $entityManager->persist($role);
                        $created++;
                    }
                }

                if ($created > 0) {
                    $entityManager->flush();
                    $this->addFlash('success', "{$created} default roles have been created successfully.");
                } else {
                    $this->addFlash('info', 'All default roles already exist.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating default roles: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_role_index', [], Response::HTTP_SEE_OTHER);
    }
}