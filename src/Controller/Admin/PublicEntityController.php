<?php

namespace App\Controller\Admin;

use App\Entity\PublicEntity;
use App\Form\PublicEntityType;
use App\Repository\PublicEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/public-entity')]
class PublicEntityController extends AbstractController
{
    #[Route('/', name: 'admin_public_entity_index', methods: ['GET'])]
    public function index(PublicEntityRepository $publicEntityRepository): Response
    {
        $publicEntities = $publicEntityRepository->createQueryBuilder('pe')
            ->leftJoin('pe.department', 'd')
            ->addSelect('d')
            ->orderBy('pe.displayOrder', 'ASC')
            ->addOrderBy('pe.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/public_entity/index.html.twig', [
            'public_entities' => $publicEntities,
        ]);
    }

    #[Route('/new', name: 'admin_public_entity_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $publicEntity = new PublicEntity();
        $form = $this->createForm(PublicEntityType::class, $publicEntity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle logo upload
                $logoFile = $form->get('logoFile')->getData();
                if ($logoFile) {
                    $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$logoFile->guessExtension();

                    $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/public_entities';
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0755, true);
                    }
                    
                    $logoFile->move($uploadsDirectory, $newFilename);
                    $publicEntity->setLogo($newFilename);
                }

                $entityManager->persist($publicEntity);
                $entityManager->flush();

                $this->addFlash('success', 'Public entity has been created successfully.');
                return $this->redirectToRoute('admin_public_entity_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating public entity: ' . $e->getMessage());
            }
        }

        return $this->render('admin/public_entity/new.html.twig', [
            'public_entity' => $publicEntity,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_public_entity_show', methods: ['GET'])]
    public function show(PublicEntity $publicEntity): Response
    {
        return $this->render('admin/public_entity/show.html.twig', [
            'public_entity' => $publicEntity,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_public_entity_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PublicEntity $publicEntity, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(PublicEntityType::class, $publicEntity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle logo removal
                $removeLogo = $form->get('removeLogo')->getData();
                if ($removeLogo) {
                    if ($publicEntity->getLogo()) {
                        $oldLogoPath = $this->getParameter('kernel.project_dir').'/public/uploads/public_entities/'.$publicEntity->getLogo();
                        if (file_exists($oldLogoPath)) {
                            unlink($oldLogoPath);
                        }
                    }
                    $publicEntity->setLogo(null);
                } else {
                    // Handle logo upload
                    $logoFile = $form->get('logoFile')->getData();
                    if ($logoFile) {
                        // Delete old logo if exists
                        if ($publicEntity->getLogo()) {
                            $oldLogoPath = $this->getParameter('kernel.project_dir').'/public/uploads/public_entities/'.$publicEntity->getLogo();
                            if (file_exists($oldLogoPath)) {
                                unlink($oldLogoPath);
                            }
                        }

                        $originalFilename = pathinfo($logoFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$logoFile->guessExtension();

                        $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/public_entities';
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $logoFile->move($uploadsDirectory, $newFilename);
                        $publicEntity->setLogo($newFilename);
                    }
                }

                $entityManager->flush();

                $this->addFlash('success', 'Public entity has been updated successfully.');
                return $this->redirectToRoute('admin_public_entity_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating public entity: ' . $e->getMessage());
            }
        }

        return $this->render('admin/public_entity/edit.html.twig', [
            'public_entity' => $publicEntity,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_public_entity_delete', methods: ['POST'])]
    public function delete(Request $request, PublicEntity $publicEntity, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$publicEntity->getId(), $request->request->get('_token'))) {
            try {
                // Delete logo file if exists
                if ($publicEntity->getLogo()) {
                    $logoPath = $this->getParameter('kernel.project_dir').'/public/uploads/public_entities/'.$publicEntity->getLogo();
                    if (file_exists($logoPath)) {
                        unlink($logoPath);
                    }
                }

                // Soft delete by setting isActive to false
                $publicEntity->setIsActive(false);
                $entityManager->flush();

                $this->addFlash('success', 'Public entity has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting public entity: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_public_entity_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_public_entity_toggle_status', methods: ['POST'])]
    public function toggleStatus(PublicEntity $publicEntity, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $publicEntity->setIsActive(!$publicEntity->isActive());
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'status' => $publicEntity->isActive(),
                'message' => 'Status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/filter', name: 'admin_public_entity_filter', methods: ['GET'])]
    public function filter(Request $request, PublicEntityRepository $publicEntityRepository): JsonResponse
    {
        $filters = [
            'department' => $request->query->get('department'),
            'status' => $request->query->get('status'),
            'search' => $request->query->get('search')
        ];

        $publicEntities = $publicEntityRepository->findPublicEntitiesWithFilters($filters);

        $data = [];
        foreach ($publicEntities as $entity) {
            $data[] = [
                'id' => $entity->getId(),
                'institutionName' => $entity->getInstitutionName(),
                'department' => $entity->getDepartment() ? $entity->getDepartment()->getName() : null,
                'status' => $entity->getStatus(),
                'email' => $entity->getEmail(),
                'phoneNumber' => $entity->getPhoneNumber(),
            ];
        }

        return new JsonResponse($data);
    }
}