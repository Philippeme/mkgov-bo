<?php

namespace App\Controller\Api;

use App\Entity\Family;
use App\Repository\FamilyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/families', name: 'api_family_')]
class FamilyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(FamilyRepository $familyRepository): JsonResponse
    {
        $families = $familyRepository->findBy(['isActive' => true], ['displayOrder' => 'ASC']);
        
        $data = $this->serializer->serialize($families, 'json', [
            'groups' => ['family:read', 'procedure:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Family $family): JsonResponse
    {
        $data = $this->serializer->serialize($family, 'json', [
            'groups' => ['family:read', 'procedure:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $family = $this->serializer->deserialize(
            $request->getContent(),
            Family::class,
            'json'
        );

        $errors = $this->validator->validate($family);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage()
                ];
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($family);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($family, 'json', [
            'groups' => ['family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, Family $family): JsonResponse
    {
        $updatedFamily = $this->serializer->deserialize(
            $request->getContent(),
            Family::class,
            'json',
            ['object_to_populate' => $family]
        );

        $errors = $this->validator->validate($updatedFamily);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage()
                ];
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        $data = $this->serializer->serialize($family, 'json', [
            'groups' => ['family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Family $family): JsonResponse
    {
        // Soft delete
        $family->setIsActive(false);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Family deleted successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/procedures', name: 'procedures', methods: ['GET'])]
    public function getProcedures(Family $family): JsonResponse
    {
        $procedures = $family->getProcedures()->filter(fn($procedure) => $procedure->isActive());
        
        $data = $this->serializer->serialize($procedures->toArray(), 'json', [
            'groups' => ['procedure:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }
}