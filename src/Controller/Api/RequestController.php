<?php

namespace App\Controller\Api;

use App\Entity\Request;
use App\Repository\RequestRepository;
use App\Repository\ProcedureRepository;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/requests', name: 'api_request_')]
class RequestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(HttpRequest $request, RequestRepository $requestRepository): JsonResponse
    {
        $status = $request->query->get('status');
        $personId = $request->query->get('person_id');
        $procedureId = $request->query->get('procedure_id');
        $priority = $request->query->get('priority');
        $paymentStatus = $request->query->get('payment_status');

        $criteria = ['isActive' => true, 'isDeleted' => false];
        
        if ($status) $criteria['status'] = $status;
        if ($personId) $criteria['person'] = $personId;
        if ($procedureId) $criteria['procedure'] = $procedureId;
        if ($priority) $criteria['priority'] = $priority;
        if ($paymentStatus) $criteria['paymentStatus'] = $paymentStatus;

        $requests = $requestRepository->findBy($criteria, ['submittedAt' => 'DESC']);

        $data = $this->serializer->serialize($requests, 'json', [
            'groups' => ['request:read', 'procedure:read', 'person:read', 'document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Request $request): JsonResponse
    {
        $data = $this->serializer->serialize($request, 'json', [
            'groups' => ['request:read', 'procedure:read', 'person:read', 'document:read', 'family:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        HttpRequest $httpRequest, 
        ProcedureRepository $procedureRepository,
        PersonRepository $personRepository
    ): JsonResponse {
        $data = json_decode($httpRequest->getContent(), true);
        
        $request = new Request();
        $request->setStatus($data['status'] ?? 'pending');
        $request->setPriority($data['priority'] ?? 'normal');
        $request->setComments($data['comments'] ?? null);
        $request->setAdminNotes($data['adminNotes'] ?? null);
        $request->setTotalCost($data['totalCost'] ?? null);
        $request->setPaidAmount($data['paidAmount'] ?? null);
        $request->setPaymentStatus($data['paymentStatus'] ?? 'pending');
        $request->setDisplayOrder($data['displayOrder'] ?? 0);

        if (isset($data['procedure_id'])) {
            $procedure = $procedureRepository->find($data['procedure_id']);
            if ($procedure) {
                $request->setProcedure($procedure);
                // Set total cost from procedure if not provided
                if (!isset($data['totalCost'])) {
                    $request->setTotalCost($procedure->getServiceCost());
                }
            }
        }

        if (isset($data['person_id'])) {
            $person = $personRepository->find($data['person_id']);
            if ($person) {
                $request->setPerson($person);
            }
        }

        if (isset($data['expectedCompletionAt'])) {
            $request->setExpectedCompletionAt(new \DateTime($data['expectedCompletionAt']));
        }

        $errors = $this->validator->validate($request);
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

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($request, 'json', [
            'groups' => ['request:read', 'procedure:read', 'person:read']
        ]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        HttpRequest $httpRequest, 
        Request $request,
        ProcedureRepository $procedureRepository,
        PersonRepository $personRepository
    ): JsonResponse {
        $data = json_decode($httpRequest->getContent(), true);

        if (isset($data['status'])) $request->setStatus($data['status']);
        if (isset($data['priority'])) $request->setPriority($data['priority']);
        if (isset($data['comments'])) $request->setComments($data['comments']);
        if (isset($data['adminNotes'])) $request->setAdminNotes($data['adminNotes']);
        if (isset($data['totalCost'])) $request->setTotalCost($data['totalCost']);
        if (isset($data['paidAmount'])) $request->setPaidAmount($data['paidAmount']);
        if (isset($data['paymentStatus'])) $request->setPaymentStatus($data['paymentStatus']);
        if (isset($data['displayOrder'])) $request->setDisplayOrder($data['displayOrder']);

        if (isset($data['procedure_id'])) {
            $procedure = $procedureRepository->find($data['procedure_id']);
            if ($procedure) {
                $request->setProcedure($procedure);
            }
        }

        if (isset($data['person_id'])) {
            $person = $personRepository->find($data['person_id']);
            if ($person) {
                $request->setPerson($person);
            }
        }

        if (isset($data['expectedCompletionAt'])) {
            $request->setExpectedCompletionAt(new \DateTime($data['expectedCompletionAt']));
        }

        $errors = $this->validator->validate($request);
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

        $data = $this->serializer->serialize($request, 'json', [
            'groups' => ['request:read', 'procedure:read', 'person:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request): JsonResponse
    {
        // Soft delete
        $request->setIsDeleted(true);
        $request->setIsActive(false);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Request deleted successfully'], Response::HTTP_OK);
    }

    #[Route('/by-reference/{reference}', name: 'by_reference', methods: ['GET'])]
    public function getByReference(string $reference, RequestRepository $requestRepository): JsonResponse
    {
        $request = $requestRepository->findOneBy([
            'reference' => $reference,
            'isActive' => true,
            'isDeleted' => false
        ]);

        if (!$request) {
            return new JsonResponse(['error' => 'Request not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->serializer->serialize($request, 'json', [
            'groups' => ['request:read', 'procedure:read', 'person:read', 'document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/by-person/{personId}', name: 'by_person', methods: ['GET'])]
    public function getByPerson(int $personId, RequestRepository $requestRepository): JsonResponse
    {
        $requests = $requestRepository->findBy([
            'person' => $personId,
            'isActive' => true,
            'isDeleted' => false
        ], ['submittedAt' => 'DESC']);

        $data = $this->serializer->serialize($requests, 'json', [
            'groups' => ['request:read', 'procedure:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/by-status/{status}', name: 'by_status', methods: ['GET'])]
    public function getByStatus(string $status, RequestRepository $requestRepository): JsonResponse
    {
        $requests = $requestRepository->findBy([
            'status' => $status,
            'isActive' => true,
            'isDeleted' => false
        ], ['submittedAt' => 'DESC']);

        $data = $this->serializer->serialize($requests, 'json', [
            'groups' => ['request:read', 'procedure:read', 'person:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function getStatistics(RequestRepository $requestRepository): JsonResponse
    {
        $stats = $requestRepository->getRequestStatistics();

        return new JsonResponse($stats, Response::HTTP_OK);
    }

    #[Route('/{id}/documents', name: 'documents', methods: ['GET'])]
    public function getDocuments(Request $request): JsonResponse
    {
        $documents = $request->getActiveDocuments();

        $data = $this->serializer->serialize($documents->toArray(), 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(HttpRequest $httpRequest, Request $request): JsonResponse
    {
        $data = json_decode($httpRequest->getContent(), true);

        if (!isset($data['status'])) {
            return new JsonResponse(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        $validStatuses = ['pending', 'processing', 'completed', 'rejected', 'cancelled'];
        if (!in_array($data['status'], $validStatuses)) {
            return new JsonResponse(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $request->setStatus($data['status']);
        
        if (isset($data['adminNotes'])) {
            $request->setAdminNotes($data['adminNotes']);
        }

        $this->entityManager->flush();

        $data = $this->serializer->serialize($request, 'json', [
            'groups' => ['request:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/payment', name: 'update_payment', methods: ['PATCH'])]
    public function updatePayment(HttpRequest $httpRequest, Request $request): JsonResponse
    {
        $data = json_decode($httpRequest->getContent(), true);

        if (isset($data['paidAmount'])) {
            $request->setPaidAmount($data['paidAmount']);
        }

        if (isset($data['paymentStatus'])) {
            $validPaymentStatuses = ['pending', 'partial', 'completed', 'pending_refund', 'revoked', 'refunded'];
            if (!in_array($data['paymentStatus'], $validPaymentStatuses)) {
                return new JsonResponse(['error' => 'Invalid payment status'], Response::HTTP_BAD_REQUEST);
            }
            $request->setPaymentStatus($data['paymentStatus']);
        }

        $errors = $this->validator->validate($request);
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

        $data = $this->serializer->serialize($request, 'json', [
            'groups' => ['request:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }
}