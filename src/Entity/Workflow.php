<?php

namespace App\Entity;

use App\Repository\WorkflowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkflowRepository::class)]
#[ORM\Table(name: 'workflows')]
#[ORM\HasLifecycleCallbacks]
class Workflow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Step name is required')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Short description is required')]
    private ?string $shortDescription = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotBlank(message: 'At least one input is required')]
    private array $inputs = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Output is required')]
    private ?string $output = null;

    #[ORM\ManyToOne(targetEntity: Procedure::class, inversedBy: 'workflows')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Procedure is required')]
    private ?Procedure $procedure = null;

    #[ORM\Column]
    private ?int $stepOrder = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private ?int $displayOrder = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $isRequired = true;

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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function setInputs(array $inputs): static
    {
        $this->inputs = $inputs;
        return $this;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(string $output): static
    {
        $this->output = $output;
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

    public function getStepOrder(): ?int
    {
        return $this->stepOrder;
    }

    public function setStepOrder(int $stepOrder): static
    {
        $this->stepOrder = $stepOrder;
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

    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    /**
     * Get available input types
     */
    public static function getAvailableInputs(): array
    {
        return [
            'Birth Certificate' => 'birth_certificate',
            'National ID Card' => 'national_id_card',
            'Authorization Letter' => 'authorization_letter',
            'Photo' => 'photo',
            'Fingerprints' => 'fingerprints',
            'Proof of Address' => 'proof_of_address',
            'Marriage Certificate' => 'marriage_certificate',
            'Divorce Certificate' => 'divorce_certificate',
            'Death Certificate' => 'death_certificate',
            'Passport Copy' => 'passport_copy',
            'Medical Certificate' => 'medical_certificate',
            'Police Clearance' => 'police_clearance',
            'Tax Certificate' => 'tax_certificate',
            'Bank Statement' => 'bank_statement',
            'Employment Letter' => 'employment_letter',
            'Educational Certificate' => 'educational_certificate',
            'Property Document' => 'property_document',
            'Payment Receipt' => 'payment_receipt',
            'Completed Form' => 'completed_form',
            'Witness Statement' => 'witness_statement'
        ];
    }

    /**
     * Get available output types
     */
    public static function getAvailableOutputs(): array
    {
        return [
            'General Information Provided' => 'general_info_provided',
            'Documents Submitted' => 'documents_submitted',
            'Payment Receipt' => 'payment_receipt',
            'Physical Discharge' => 'physical_discharge',
            'Verification Completed' => 'verification_completed',
            'Approval Granted' => 'approval_granted',
            'Certificate Generated' => 'certificate_generated',
            'Document Ready for Collection' => 'document_ready_collection',
            'Biometric Data Captured' => 'biometric_captured',
            'Interview Completed' => 'interview_completed',
            'Background Check Cleared' => 'background_check_cleared',
            'Medical Examination Passed' => 'medical_exam_passed',
            'Step Completed Successfully' => 'step_completed'
        ];
    }

    public function getInputsLabels(): array
    {
        $availableInputs = self::getAvailableInputs();
        $labels = [];
        
        foreach ($this->inputs as $input) {
            $labels[] = array_search($input, $availableInputs) ?: $input;
        }
        
        return $labels;
    }

    public function getOutputLabel(): string
    {
        $availableOutputs = self::getAvailableOutputs();
        return array_search($this->output, $availableOutputs) ?: $this->output;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}