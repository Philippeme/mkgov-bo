<?php

namespace App\Entity;

use App\Repository\ProcedureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProcedureRepository::class)]
#[ORM\Table(name: 'procedures')]
#[ORM\HasLifecycleCallbacks]
class Procedure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Procedure name is required')]
    #[Assert\Length(max: 255)]
    private ?string $pname = null;

    #[ORM\ManyToOne(targetEntity: Family::class, inversedBy: 'procedures')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Family is required')]
    private ?Family $family = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Short description is required')]
    private ?string $shortdesc = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Long description is required')]
    private ?string $longdesc = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Process time is required')]
    private ?string $processtime = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Service cost is required')]
    #[Assert\PositiveOrZero(message: 'Service cost must be positive or zero')]
    private ?string $servicecost = null; 

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legaltext = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private ?bool $published = true;

    #[ORM\Column]
    private ?int $displayOrder = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'procedure', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'procedure', targetEntity: Request::class)]
    private Collection $requests;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->documents = new ArrayCollection();
        $this->requests = new ArrayCollection();
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

    public function getPname(): ?string
    {
        return $this->pname;
    }

    public function setPname(string $pname): static
    {
        $this->pname = $pname;
        return $this;
    }

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function setFamily(?Family $family): static
    {
        $this->family = $family;
        return $this;
    }

    public function getShortDesc(): ?string
    {
        return $this->shortdesc;
    }

    public function setShortDesc(string $shortdesc): static
    {
        $this->shortdesc = $shortdesc;
        return $this;
    }

    public function getLongDesc(): ?string
    {
        return $this->longdesc;
    }

    public function setLongDesc(string $longdesc): static
    {
        $this->longdesc = $longdesc;
        return $this;
    }

    public function getProcessTime(): ?string
    {
        return $this->processtime;
    }

    public function setProcessTime(string $processtime): static
    {
        $this->processtime = $processtime;
        return $this;
    }

    public function getServiceCost(): ?string
    {
        return $this->servicecost;
    }

    public function setServiceCost(string $servicecost): static
    {
        $this->servicecost = $servicecost;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getLegalText(): ?string
    {
        return $this->legaltext;
    }

    public function setLegalText(?string $legaltext): static
    {
        $this->legaltext = $legaltext;
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

    public function isPublished(): ?bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;
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
            $document->setProcedure($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getProcedure() === $this) {
                $document->setProcedure(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Request>
     */
    public function getRequests(): Collection
    {
        return $this->requests;
    }

    public function addRequest(Request $request): static
    {
        if (!$this->requests->contains($request)) {
            $this->requests->add($request);
            $request->setProcedure($this);
        }

        return $this;
    }

    public function removeRequest(Request $request): static
    {
        if ($this->requests->removeElement($request)) {
            if ($request->getProcedure() === $this) {
                $request->setProcedure(null);
            }
        }

        return $this;
    }

    public function getActiveRequests(): Collection
    {
        return $this->requests->filter(fn(Request $req) => $req->isActive() && !$req->isDeleted());
    }

    public function getPendingRequests(): Collection
    {
        return $this->requests->filter(fn(Request $req) => 
            $req->isActive() && !$req->isDeleted() && $req->getStatus() === 'pending'
        );
    }

    public function getCompletedRequests(): Collection
    {
        return $this->requests->filter(fn(Request $req) => 
            $req->isActive() && !$req->isDeleted() && $req->getStatus() === 'completed'
        );
    }

    public function getInputDocuments(): Collection
    {
        return $this->documents->filter(fn(Document $doc) => $doc->getType() === 'input' && $doc->isActive());
    }

    public function getOutputDocuments(): Collection
    {
        return $this->documents->filter(fn(Document $doc) => $doc->getType() === 'output' && $doc->isActive());
    }

    public function __toString(): string
    {
        return $this->pname ?? '';
    }
}