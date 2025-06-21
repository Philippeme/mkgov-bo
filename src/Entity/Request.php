<?php

namespace App\Entity;

use App\Repository\RequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RequestRepository::class)]
#[ORM\Table(name: 'requests')]
#[ORM\HasLifecycleCallbacks]
class Request
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne(targetEntity: Procedure::class, inversedBy: 'requests')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Procedure is required')]
    private ?Procedure $procedure = null;

    #[ORM\ManyToOne(targetEntity: Person::class, inversedBy: 'requests')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Person is required')]
    private ?Person $person = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Choice(
        choices: ['pending', 'processing', 'completed', 'rejected', 'cancelled'],
        message: 'Invalid status'
    )]
    private ?string $status = 'pending';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Priority is required')]
    #[Assert\Choice(
        choices: ['low', 'normal', 'high', 'urgent'],
        message: 'Invalid priority'
    )]
    private ?string $priority = 'normal';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comments = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalCost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $paidAmount = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(
        choices: ['pending', 'partial', 'completed', 'refunded'],
        message: 'Invalid payment status'
    )]
    private ?string $paymentStatus = 'pending';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expectedCompletionAt = null;

    #[ORM\Column]
    private ?int $displayOrder = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $isDeleted = false;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: Document::class)]
    private Collection $documents;

    public function __construct()
    {
        $this->submittedAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->documents = new ArrayCollection();
        $this->generateReference();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    private function generateReference(): void
    {
        $this->reference = 'REQ-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        // Auto-set completion date when status changes to completed
        if ($status === 'completed' && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary'
        };
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPriorityBadgeClass(): string
    {
        return match($this->priority) {
            'low' => 'secondary',
            'normal' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
            default => 'primary'
        };
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): static
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getTotalCost(): ?string
    {
        return $this->totalCost;
    }

    public function setTotalCost(?string $totalCost): static
    {
        $this->totalCost = $totalCost;
        return $this;
    }

    public function getPaidAmount(): ?string
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(?string $paidAmount): static
    {
        $this->paidAmount = $paidAmount;
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getPaymentStatusBadgeClass(): string
    {
        return match($this->paymentStatus) {
            'pending' => 'warning',
            'partial' => 'info',
            'completed' => 'success',
            'refunded' => 'secondary',
            default => 'warning'
        };
    }

    public function getSubmittedAt(): ?\DateTimeInterface
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeInterface $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
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

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getExpectedCompletionAt(): ?\DateTimeInterface
    {
        return $this->expectedCompletionAt;
    }

    public function setExpectedCompletionAt(?\DateTimeInterface $expectedCompletionAt): static
    {
        $this->expectedCompletionAt = $expectedCompletionAt;
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

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setRequest($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getRequest() === $this) {
                $document->setRequest(null);
            }
        }

        return $this;
    }

    public function getActiveDocuments(): Collection
    {
        return $this->documents->filter(fn(Document $doc) => $doc->isActive() && !$doc->isDeleted());
    }

    public function getDaysInProgress(): int
    {
        $endDate = $this->completedAt ?? new \DateTime();
        return $this->submittedAt->diff($endDate)->days;
    }

    public function isOverdue(): bool
    {
        if (!$this->expectedCompletionAt || $this->status === 'completed') {
            return false;
        }
        
        return new \DateTime() > $this->expectedCompletionAt;
    }

    public function getProgressPercentage(): int
    {
        return match($this->status) {
            'pending' => 10,
            'processing' => 50,
            'completed' => 100,
            'rejected', 'cancelled' => 0,
            default => 0
        };
    }

    public function __toString(): string
    {
        return $this->reference ?? '';
    }
}