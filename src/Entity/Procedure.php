<?php

namespace App\Entity;

use App\Repository\ProcedureRepository;
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
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(max: 255)]
    private ?string $pname = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Family is required')]
    private ?string $family = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Short description is required')]
    private ?string $shortdesc = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Long description is required')]
    private ?string $longdesc = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Process time is required')]
    private ?string $processtime = null;

    #[ORM\Column(type: Types::DECIMAL)]
    #[Assert\NotBlank(message: 'Service cost is required')]
    private ?string $servicecost = null; 

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private ?bool $published = true;

    #[ORM\Column]
    private ?int $displayOrder = 0;

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

    public function getPname(): ?string
    {
        return $this->pname;
    }

    public function setPname(string $pname): static
    {
        $this->pname = $pname;
        return $this;
    }

    public function getFamily(): ?string
    {
        return $this->family;
    }

    public function setFamily(string $family): static
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

    public function getServiceCost(): ?string
    {
        return $this->servicecost;
    }

    public function setServiceCost(string $servicecost): static
    {
        $this->servicecost = $servicecost;
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
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
}