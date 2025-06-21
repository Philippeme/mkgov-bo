<?php

namespace App\Controller\Admin;

use App\Entity\Department;
use App\Form\DepartmentType;
use App\Repository\DepartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/department')]
class DepartmentController extends AbstractController
{
    #[Route('/', name: 'admin_department_index', methods: ['GET'])]
    public function index(DepartmentRepository $departmentRepository): Response
    {
        $departments = $departmentRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC']);
        
        return $this->render('admin/department/index.html.twig', [
            'departments' => $departments,
        ]);
    }

    #[Route('/new', name: 'admin_department_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $department = new Department();
        $form = $this->createForm(DepartmentType::class, $department);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                $entityManager->persist($department);
                $entityManager->flush();

                $this->addFlash('success', 'Department has been created successfully.');
                return $this->redirectToRoute('admin_department_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating department: ' . $e->getMessage());
            }
        }

        return $this->render('admin/department/new.html.twig', [
            'department' => $department,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_department_show', methods: ['GET'])]
    public function show(Department $department): Response
    {
        return $this->render('admin/department/show.html.twig', [
            'department' => $department,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_department_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Department $department, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(DepartmentType::class, $department);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                $entityManager->flush();

                $this->addFlash('success', 'Department has been updated successfully.');
                return $this->redirectToRoute('admin_department_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating department: ' . $e->getMessage());
            }
        }

        return $this->render('admin/department/edit.html.twig', [
            'department' => $department,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_department_delete', methods: ['POST'])]
    public function delete(Request $request, Department $department, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$department->getId(), $request->request->get('_token'))) {
            try {
                // Check if department has associated public entities
                if ($department->getPublicEntities()->count() > 0) {
                    $this->addFlash('error', 'Cannot delete department with associated public entities.');
                    return $this->redirectToRoute('admin_department_index', [], Response::HTTP_SEE_OTHER);
                }

                // Soft delete by setting isActive to false
                $department->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Department has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting department: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_department_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_department_toggle_status', methods: ['POST'])]
    public function toggleStatus(Department $department, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $department->setIsActive(!$department->isActive());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $department->isActive(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

}