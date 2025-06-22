<?php

namespace App\Entity;

use App\Repository\PublicEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PublicEntityRepository::class)]
#[ORM\Table(name: 'public_entities')]
#[ORM\HasLifecycleCallbacks]
class PublicEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Institution name is required')]
    #[Assert\Length(max: 255)]
    private ?string $institutionName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Headquarters address is required')]
    private ?string $headquartersAddress = null;

    #[ORM\ManyToOne(targetEntity: Department::class, inversedBy: 'publicEntities')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Department is required')]
    private ?Department $department = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex('/^[\+]?[0-9\s\-\(\)]+$/', message: 'Please enter a valid phone number')]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'Please enter a valid email address')]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactPersonName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'Please enter a valid contact person email')]
    private ?string $contactPersonEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex('/^[\+]?[0-9\s\-\(\)]+$/', message: 'Please enter a valid contact person phone number')]
    private ?string $contactPersonPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = 'active';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private ?int $displayOrder = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'providingAdministration', targetEntity: Procedure::class)]
    private Collection $procedures;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->procedures = new ArrayCollection();
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

    public function getInstitutionName(): ?string
    {
        return $this->institutionName;
    }

    public function setInstitutionName(string $institutionName): static
    {
        $this->institutionName = $institutionName;
        return $this;
    }

    public function getHeadquartersAddress(): ?string
    {
        return $this->headquartersAddress;
    }

    public function setHeadquartersAddress(string $headquartersAddress): static
    {
        $this->headquartersAddress = $headquartersAddress;
        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getContactPersonName(): ?string
    {
        return $this->contactPersonName;
    }

    public function setContactPersonName(?string $contactPersonName): static
    {
        $this->contactPersonName = $contactPersonName;
        return $this;
    }

    public function getContactPersonEmail(): ?string
    {
        return $this->contactPersonEmail;
    }

    public function setContactPersonEmail(?string $contactPersonEmail): static
    {
        $this->contactPersonEmail = $contactPersonEmail;
        return $this;
    }

    public function getContactPersonPhone(): ?string
    {
        return $this->contactPersonPhone;
    }

    public function setContactPersonPhone(?string $contactPersonPhone): static
    {
        $this->contactPersonPhone = $contactPersonPhone;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
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
     * @return Collection<int, Procedure>
     */
    public function getProcedures(): Collection
    {
        return $this->procedures;
    }

    public function addProcedure(Procedure $procedure): static
    {
        if (!$this->procedures->contains($procedure)) {
            $this->procedures->add($procedure);
            $procedure->setProvidingAdministration($this);
        }

        return $this;
    }

    public function removeProcedure(Procedure $procedure): static
    {
        if ($this->procedures->removeElement($procedure)) {
            if ($procedure->getProvidingAdministration() === $this) {
                $procedure->setProvidingAdministration(null);
            }
        }

        return $this;
    }

    public function getActiveProcedures(): Collection
    {
        return $this->procedures->filter(fn(Procedure $proc) => $proc->isActive());
    }

    public function getPublishedProcedures(): Collection
    {
        return $this->procedures->filter(fn(Procedure $proc) => $proc->isActive() && $proc->isPublished());
    }

    public function getProceduresByFamily($family): Collection
    {
        return $this->procedures->filter(fn(Procedure $proc) => 
            $proc->isActive() && $proc->getFamily() === $family
        );
    }

    public function getProceduresGroupedByFamily(): array
    {
        $grouped = [];
        foreach ($this->getActiveProcedures() as $procedure) {
            $familyName = $procedure->getFamily() ? $procedure->getFamily()->getFname() : 'No Family';
            if (!isset($grouped[$familyName])) {
                $grouped[$familyName] = [];
            }
            $grouped[$familyName][] = $procedure;
        }
        ksort($grouped);
        return $grouped;
    }

    public function getTotalCostOfProcedures(): float
    {
        $total = 0;
        foreach ($this->getActiveProcedures() as $procedure) {
            $total += (float)$procedure->getServiceCost();
        }
        return $total;
    }

    public function __toString(): string
    {
        return $this->institutionName ?? '';
    }
}