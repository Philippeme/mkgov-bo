<?php

namespace App\Controller\Api;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Repository\ProcedureRepository;
use App\Repository\PersonRepository;
use App\Repository\RequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/documents', name: 'api_document_')]
class DocumentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private SluggerInterface $slugger
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $documentRepository): JsonResponse
    {
        $type = $request->query->get('type');
        $status = $request->query->get('status');
        $procedureId = $request->query->get('procedure_id');
        $personId = $request->query->get('person_id');
        $requestId = $request->query->get('request_id');
        $isRequired = $request->query->get('is_required');

        $criteria = ['isActive' => true, 'isDeleted' => false];
        
        if ($type) $criteria['type'] = $type;
        if ($status) $criteria['status'] = $status;
        if ($procedureId) $criteria['procedure'] = $procedureId;
        if ($personId) $criteria['person'] = $personId;
        if ($requestId) $criteria['request'] = $requestId;
        if ($isRequired !== null) $criteria['isRequired'] = filter_var($isRequired, FILTER_VALIDATE_BOOLEAN);

        $documents = $documentRepository->findBy($criteria, ['displayOrder' => 'ASC', 'createdAt' => 'DESC']);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read', 'procedure:read', 'person:read', 'request:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Document $document): JsonResponse
    {
        $data = $this->serializer->serialize($document, 'json', [
            'groups' => ['document:read', 'procedure:read', 'person:read', 'request:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        ProcedureRepository $procedureRepository,
        PersonRepository $personRepository,
        RequestRepository $requestRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $document = new Document();
        $document->setName($data['name'] ?? '');
        $document->setType($data['type'] ?? 'input');
        $document->setDescription($data['description'] ?? null);
        $document->setStatus($data['status'] ?? 'draft');
        $document->setIsRequired($data['isRequired'] ?? false);
        $document->setDisplayOrder($data['displayOrder'] ?? 0);

        if (isset($data['procedure_id'])) {
            $procedure = $procedureRepository->find($data['procedure_id']);
            if ($procedure) {
                $document->setProcedure($procedure);
            }
        }

        if (isset($data['person_id'])) {
            $person = $personRepository->find($data['person_id']);
            if ($person) {
                $document->setPerson($person);
            }
        }

        if (isset($data['request_id'])) {
            $requestEntity = $requestRepository->find($data['request_id']);
            if ($requestEntity) {
                $document->setRequest($requestEntity);
            }
        }

        if (isset($data['expirationDate'])) {
            $document->setExpirationDate(new \DateTime($data['expirationDate']));
        }

        $errors = $this->validator->validate($document);
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

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($document, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        Request $request,
        Document $document,
        ProcedureRepository $procedureRepository,
        PersonRepository $personRepository,
        RequestRepository $requestRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $document->setName($data['name']);
        if (isset($data['type'])) $document->setType($data['type']);
        if (isset($data['description'])) $document->setDescription($data['description']);
        if (isset($data['status'])) $document->setStatus($data['status']);
        if (isset($data['isRequired'])) $document->setIsRequired($data['isRequired']);
        if (isset($data['displayOrder'])) $document->setDisplayOrder($data['displayOrder']);

        if (isset($data['procedure_id'])) {
            $procedure = $procedureRepository->find($data['procedure_id']);
            $document->setProcedure($procedure);
        }

        if (isset($data['person_id'])) {
            $person = $personRepository->find($data['person_id']);
            $document->setPerson($person);
        }

        if (isset($data['request_id'])) {
            $requestEntity = $requestRepository->find($data['request_id']);
            $document->setRequest($requestEntity);
        }

        if (isset($data['expirationDate'])) {
            $document->setExpirationDate($data['expirationDate'] ? new \DateTime($data['expirationDate']) : null);
        }

        $errors = $this->validator->validate($document);
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

        $data = $this->serializer->serialize($document, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Document $document): JsonResponse
    {
        // Soft delete
        $document->setIsDeleted(true);
        $document->setIsActive(false);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Document deleted successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/upload', name: 'upload', methods: ['POST'])]
    public function uploadFile(Request $request, Document $document): JsonResponse
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        // Validate file
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($uploadedFile->getSize() > $maxSize) {
            return new JsonResponse(['error' => 'File too large'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
            return new JsonResponse(['error' => 'Invalid file type'], Response::HTTP_BAD_REQUEST);
        }

        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

        try {
            $uploadedFile->move(
                $this->getParameter('documents_directory'),
                $newFilename
            );

            $document->setFilePath('documents/' . $newFilename);
            $document->setFileSize($this->formatBytes($uploadedFile->getSize()));
            $document->setMimeType($uploadedFile->getMimeType());
            $document->setStatus('pending');

            $this->entityManager->flush();

            $data = $this->serializer->serialize($document, 'json', [
                'groups' => ['document:read']
            ]);

            return new JsonResponse($data, Response::HTTP_OK, [], true);

        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/download', name: 'download', methods: ['GET'])]
    public function downloadFile(Document $document): Response
    {
        if (!$document->getFilePath()) {
            return new JsonResponse(['error' => 'No file available'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getFilePath();
        
        if (!file_exists($filePath)) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($filePath);
    }

    #[Route('/by-procedure/{procedureId}', name: 'by_procedure', methods: ['GET'])]
    public function getByProcedure(int $procedureId, DocumentRepository $documentRepository): JsonResponse
    {
        $documents = $documentRepository->findBy([
            'procedure' => $procedureId,
            'isActive' => true,
            'isDeleted' => false
        ], ['displayOrder' => 'ASC']);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/by-request/{requestId}', name: 'by_request', methods: ['GET'])]
    public function getByRequest(int $requestId, DocumentRepository $documentRepository): JsonResponse
    {
        $documents = $documentRepository->findBy([
            'request' => $requestId,
            'isActive' => true,
            'isDeleted' => false
        ], ['displayOrder' => 'ASC']);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/by-person/{personId}', name: 'by_person', methods: ['GET'])]
    public function getByPerson(int $personId, DocumentRepository $documentRepository): JsonResponse
    {
        $documents = $documentRepository->findBy([
            'person' => $personId,
            'isActive' => true,
            'isDeleted' => false
        ], ['createdAt' => 'DESC']);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/by-type/{type}', name: 'by_type', methods: ['GET'])]
    public function getByType(string $type, DocumentRepository $documentRepository): JsonResponse
    {
        if (!in_array($type, ['input', 'output'])) {
            return new JsonResponse(['error' => 'Invalid document type'], Response::HTTP_BAD_REQUEST);
        }

        $documents = $documentRepository->findBy([
            'type' => $type,
            'isActive' => true,
            'isDeleted' => false
        ], ['createdAt' => 'DESC']);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/by-status/{status}', name: 'by_status', methods: ['GET'])]
    public function getByStatus(string $status, DocumentRepository $documentRepository): JsonResponse
    {
        $validStatuses = ['draft', 'pending', 'approved', 'rejected', 'expired', 'active'];
        if (!in_array($status, $validStatuses)) {
            return new JsonResponse(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $documents = $documentRepository->findBy([
            'status' => $status,
            'isActive' => true,
            'isDeleted' => false
        ], ['createdAt' => 'DESC']);

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(Request $request, Document $document): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['status'])) {
            return new JsonResponse(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        $validStatuses = ['draft', 'pending', 'approved', 'rejected', 'expired', 'active'];
        if (!in_array($data['status'], $validStatuses)) {
            return new JsonResponse(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $document->setStatus($data['status']);
        $this->entityManager->flush();

        $data = $this->serializer->serialize($document, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    #[Route('/expired', name: 'expired', methods: ['GET'])]
    public function getExpired(DocumentRepository $documentRepository): JsonResponse
    {
        $documents = $documentRepository->createQueryBuilder('d')
            ->where('d.expirationDate IS NOT NULL')
            ->andWhere('d.expirationDate <= :now')
            ->andWhere('d.isActive = true')
            ->andWhere('d.isDeleted = false')
            ->setParameter('now', new \DateTime())
            ->orderBy('d.expirationDate', 'ASC')
            ->getQuery()
            ->getResult();

        $data = $this->serializer->serialize($documents, 'json', [
            'groups' => ['document:read']
        ]);

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}