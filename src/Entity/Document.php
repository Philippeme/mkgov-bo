<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document:read', 'procedure:read', 'request:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Document name is required')]
    #[Assert\Length(max: 255)]
    #[Groups(['document:read', 'document:write', 'procedure:read', 'request:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Document type is required')]
    #[Assert\Choice(choices: ['input', 'output'], message: 'Type must be either input or output')]
    #[Groups(['document:read', 'document:write'])]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['document:read', 'document:write', 'procedure:read', 'request:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $fileSize = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['document:read'])]
    private ?string $mimeType = null;

    #[ORM\ManyToOne(targetEntity: Procedure::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Procedure $procedure = null;

    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['document:read', 'document:write'])]
    private ?Person $person = null;

    #[ORM\ManyToOne(targetEntity: Request::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:read', 'document:write'])]
    private ?Request $request = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['document:read', 'document:write'])]
    private ?\DateTimeInterface $expirationDate = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(
        choices: ['draft', 'pending', 'approved', 'rejected', 'expired', 'active'], 
        message: 'Invalid status'
    )]
    #[Groups(['document:read', 'document:write'])]
    private ?string $status = 'draft';

    #[ORM\Column]
    #[Groups(['document:read', 'document:write', 'procedure:read', 'request:read'])]
    private ?bool $isRequired = false;

    #[ORM\Column]
    #[Groups(['document:read', 'document:write'])]
    private ?bool $isActive = true;

    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?bool $isDeleted = false;

    #[ORM\Column]
    #[Groups(['document:read', 'document:write'])]
    private ?int $displayOrder = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['document:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['document:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getFileSize(): ?string
    {
        return $this->fileSize;
    }

    public function setFileSize(?string $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getProcedure(): ?Procedure
    {
        return $this->procedure;
    }

    public function setProcedure(?Procedure $procedure): static
    {
        $this->procedure = $procedure;
        return $this;
    }

    public function getPerson(): ?Person
    {
        return $this->person;
    }

    public function setPerson(?Person $person): static
    {
        $this->person = $person;
        return $this;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeInterface $expirationDate): static
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'approved', 'active' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            'expired' => 'secondary',
            'draft' => 'info',
            default => 'light'
        };
    }

    public function isExpired(): bool
    {
        if (!$this->expirationDate) {
            return false;
        }
        return $this->expirationDate <= new \DateTime();
    }

    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getMainAssociation(): array
    {
        if ($this->request) {
            return [
                'type' => 'request',
                'entity' => $this->request,
                'label' => $this->request->getReference(),
                'route' => 'admin_request_show',
                'icon' => 'bi-journal-text'
            ];
        }
        
        if ($this->procedure) {
            return [
                'type' => 'procedure',
                'entity' => $this->procedure,
                'label' => $this->procedure->getPname(),
                'route' => 'admin_procedure_show',
                'icon' => 'bi-gear'
            ];
        }
        
        if ($this->person) {
            return [
                'type' => 'person',
                'entity' => $this->person,
                'label' => $this->person->getFullName(),
                'route' => 'admin_person_show',
                'icon' => 'bi-person'
            ];
        }
        
        return [
            'type' => 'none',
            'entity' => null,
            'label' => 'No associations',
            'route' => null,
            'icon' => 'bi-slash-circle'
        ];
    }

    public function belongsToRequest(Request $request): bool
    {
        return $this->request && $this->request->getId() === $request->getId();
    }

    public function getContextInfo(): string
    {
        $parts = [];
        
        if ($this->request) {
            $parts[] = "Request: {$this->request->getReference()}";
        }
        
        if ($this->procedure) {
            $parts[] = "Procedure: {$this->procedure->getPname()}";
        }
        
        if ($this->person) {
            $parts[] = "Citizen: {$this->person->getFullName()}";
        }
        
        return !empty($parts) ? implode(' | ', $parts) : 'No context';
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}