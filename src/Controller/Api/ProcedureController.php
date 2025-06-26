<?php

namespace App\Controller\Api;

use App\Entity\Procedure;
use App\Repository\ProcedureRepository;
use App\Repository\FamilyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/procedures', name: 'api_procedure_')]
class ProcedureController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProcedureRepository $procedureRepository): JsonResponse
    {
        $published = $request->query->get('published');
        $familyId = $request->query->get('family');
        $search = $request->query->get('search');

        $criteria = ['isActive' => true];
        
        if ($published !== null) {
            $criteria['published'] = filter_var($published, FILTER_VALIDATE_BOOLEAN);
        }

        if ($familyId) {
            $criteria['family'] = $familyId;
        }

        if ($search) {
            $procedures = $procedureRepository->searchProcedures($search, $criteria);
        } else {
            $procedures = $procedureRepository->findBy($criteria, ['displayOrder' => 'ASC']);
        }

        $data = $this->serializer->serialize($procedures, 'json', [
            'groups' => ['procedure:read', 'family:read', 'document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Procedure $procedure): JsonResponse
    {
        $data = $this->serializer->serialize($procedure, 'json', [
            'groups' => ['procedure:read', 'family:read', 'document:read', 'workflow:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, FamilyRepository $familyRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $procedure = new Procedure();
        $procedure->setPname($data['pname'] ?? '');
        $procedure->setShortDesc($data['shortdesc'] ?? '');
        $procedure->setLongDesc($data['longdesc'] ?? '');
        $procedure->setProcessTime($data['processtime'] ?? '');
        $procedure->setServiceCost($data['servicecost'] ?? '0');
        $procedure->setImage($data['image'] ?? null);
        $procedure->setLegalText($data['legaltext'] ?? null);
        $procedure->setPublished($data['published'] ?? true);
        $procedure->setDisplayOrder($data['displayOrder'] ?? 0);

        if (isset($data['family_id'])) {
            $family = $familyRepository->find($data['family_id']);
            if ($family) {
                $procedure->setFamily($family);
            }
        }

        $errors = $this->validator->validate($procedure);
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

        $this->entityManager->persist($procedure);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($procedure, 'json', [
            'groups' => ['procedure:read', 'family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, Procedure $procedure, FamilyRepository $familyRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['pname'])) $procedure->setPname($data['pname']);
        if (isset($data['shortdesc'])) $procedure->setShortDesc($data['shortdesc']);
        if (isset($data['longdesc'])) $procedure->setLongDesc($data['longdesc']);
        if (isset($data['processtime'])) $procedure->setProcessTime($data['processtime']);
        if (isset($data['servicecost'])) $procedure->setServiceCost($data['servicecost']);
        if (isset($data['image'])) $procedure->setImage($data['image']);
        if (isset($data['legaltext'])) $procedure->setLegalText($data['legaltext']);
        if (isset($data['published'])) $procedure->setPublished($data['published']);
        if (isset($data['displayOrder'])) $procedure->setDisplayOrder($data['displayOrder']);

        if (isset($data['family_id'])) {
            $family = $familyRepository->find($data['family_id']);
            if ($family) {
                $procedure->setFamily($family);
            }
        }

        $errors = $this->validator->validate($procedure);
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

        $data = $this->serializer->serialize($procedure, 'json', [
            'groups' => ['procedure:read', 'family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Procedure $procedure): JsonResponse
    {
        // Soft delete
        $procedure->setIsActive(false);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Procedure deleted successfully'], Response::HTTP_OK);
    }

    #[Route('/by-family/{familyId}', name: 'by_family', methods: ['GET'])]
    public function getByFamily(int $familyId, ProcedureRepository $procedureRepository): JsonResponse
    {
        $procedures = $procedureRepository->findBy([
            'family' => $familyId,
            'isActive' => true
        ], ['displayOrder' => 'ASC']);

        $data = $this->serializer->serialize($procedures, 'json', [
            'groups' => ['procedure:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/published', name: 'published', methods: ['GET'])]
    public function getPublished(ProcedureRepository $procedureRepository): JsonResponse
    {
        $procedures = $procedureRepository->findBy([
            'published' => true,
            'isActive' => true
        ], ['displayOrder' => 'ASC']);

        $data = $this->serializer->serialize($procedures, 'json', [
            'groups' => ['procedure:read', 'family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, ProcedureRepository $procedureRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return new JsonResponse(['error' => 'Search query is required'], Response::HTTP_BAD_REQUEST);
        }

        $procedures = $procedureRepository->searchProcedures($query);

        $data = $this->serializer->serialize($procedures, 'json', [
            'groups' => ['procedure:read', 'family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/documents', name: 'documents', methods: ['GET'])]
    public function getDocuments(Procedure $procedure): JsonResponse
    {
        $documents = $procedure->getActiveDocuments();

        $data = $this->serializer->serialize($documents->toArray(), 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/requests', name: 'requests', methods: ['GET'])]
    public function getRequests(Procedure $procedure): JsonResponse
    {
        $requests = $procedure->getActiveRequests();

        $data = $this->serializer->serialize($requests->toArray(), 'json', [
            'groups' => ['request:read', 'person:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }
}