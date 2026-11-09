<?php

namespace App\Entity;

use App\Repository\LoanApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanApplicationRepository::class)]
#[ORM\Table(name: 'loan_applications')]
#[ORM\Index(name: 'idx_user_id', columns: ['user_id'])]
class LoanApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'loanApplications')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'loan_type', type: 'string', length: 30)]
    private string $loanType;

    #[ORM\Column(name: 'id_type', type: 'string', length: 50)]
    private string $idType;

    #[ORM\Column(name: 'employment_status', type: 'string', length: 20)]
    private string $employmentStatus;

    #[ORM\Column(name: 'occupation', type: 'string', length: 100)]
    private string $occupation;

    #[ORM\Column(name: 'employer_name', type: 'string', length: 150, nullable: true)]
    private ?string $employerName = null;

    #[ORM\Column(name: 'length_of_employment', type: 'string', length: 50, nullable: true)]
    private ?string $lengthOfEmployment = null;

    #[ORM\Column(name: 'employer_contact_number', type: 'string', length: 20, nullable: true)]
    private ?string $employerContactNumber = null;

    #[ORM\Column(name: 'business_name', type: 'string', length: 150, nullable: true)]
    private ?string $businessName = null;

    #[ORM\Column(name: 'business_type', type: 'string', length: 100, nullable: true)]
    private ?string $businessType = null;

    #[ORM\Column(name: 'monthly_income', type: 'decimal', precision: 12, scale: 2)]
    private string $monthlyIncome;

    #[ORM\Column(name: 'payment_frequency', type: 'string', length: 20, options: ['default' => 'monthly'])]
    private string $paymentFrequency = 'monthly';

    #[ORM\Column(name: 'interest_rate', type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $interestRate = null;

    #[ORM\Column(name: 'next_due_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextDueDate = null;

    #[ORM\Column(name: 'amount', type: 'decimal', precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(name: 'term_months', type: 'smallint', options: ['unsigned' => true])]
    private int $termMonths;

    #[ORM\Column(name: 'purpose', type: 'string', length: 500)]
    private string $purpose;

    #[ORM\Column(name: 'valid_id_path', type: 'string', length: 255)]
    private string $validIdPath;

    #[ORM\Column(name: 'proof_of_income_path', type: 'string', length: 255)]
    private string $proofOfIncomePath;

    #[ORM\Column(name: 'proof_of_address_path', type: 'string', length: 255)]
    private string $proofOfAddressPath;

    #[ORM\Column(name: 'employment_certificate_path', type: 'string', length: 255, nullable: true)]
    private ?string $employmentCertificatePath = null;

    #[ORM\Column(name: 'status', type: 'string', length: 9, options: ['default' => 'pending'], columnDefinition: "enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'")]
    private string $status = 'pending';

    #[ORM\Column(name: 'submitted_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $submittedAt;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'loanApplication')]
    private Collection $payments;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getLoanType(): string
    {
        return $this->loanType;
    }

    public function setLoanType(string $loanType): self
    {
        $this->loanType = $loanType;

        return $this;
    }

    public function getIdType(): string
    {
        return $this->idType;
    }

    public function setIdType(string $idType): self
    {
        $this->idType = $idType;

        return $this;
    }

    public function getEmploymentStatus(): string
    {
        return $this->employmentStatus;
    }

    public function setEmploymentStatus(string $employmentStatus): self
    {
        $this->employmentStatus = $employmentStatus;

        return $this;
    }

    public function getOccupation(): string
    {
        return $this->occupation;
    }

    public function setOccupation(string $occupation): self
    {
        $this->occupation = $occupation;

        return $this;
    }

    public function getEmployerName(): ?string
    {
        return $this->employerName;
    }

    public function setEmployerName(?string $employerName): self
    {
        $this->employerName = $employerName;

        return $this;
    }

    public function getLengthOfEmployment(): ?string
    {
        return $this->lengthOfEmployment;
    }

    public function setLengthOfEmployment(?string $lengthOfEmployment): self
    {
        $this->lengthOfEmployment = $lengthOfEmployment;

        return $this;
    }

    public function getEmployerContactNumber(): ?string
    {
        return $this->employerContactNumber;
    }

    public function setEmployerContactNumber(?string $employerContactNumber): self
    {
        $this->employerContactNumber = $employerContactNumber;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function setBusinessName(?string $businessName): self
    {
        $this->businessName = $businessName;

        return $this;
    }

    public function getBusinessType(): ?string
    {
        return $this->businessType;
    }

    public function setBusinessType(?string $businessType): self
    {
        $this->businessType = $businessType;

        return $this;
    }

    public function getMonthlyIncome(): string
    {
        return $this->monthlyIncome;
    }

    public function setMonthlyIncome(string $monthlyIncome): self
    {
        $this->monthlyIncome = $monthlyIncome;

        return $this;
    }

    public function getPaymentFrequency(): string
    {
        return $this->paymentFrequency;
    }

    public function setPaymentFrequency(string $paymentFrequency): self
    {
        $this->paymentFrequency = $paymentFrequency;

        return $this;
    }

    public function getInterestRate(): ?string
    {
        return $this->interestRate;
    }

    public function setInterestRate(?string $interestRate): self
    {
        $this->interestRate = $interestRate;

        return $this;
    }

    public function getNextDueDate(): ?\DateTimeImmutable
    {
        return $this->nextDueDate;
    }

    public function setNextDueDate(?\DateTimeImmutable $nextDueDate): self
    {
        $this->nextDueDate = $nextDueDate;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getTermMonths(): int
    {
        return $this->termMonths;
    }

    public function setTermMonths(int $termMonths): self
    {
        $this->termMonths = $termMonths;

        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getValidIdPath(): string
    {
        return $this->validIdPath;
    }

    public function setValidIdPath(string $validIdPath): self
    {
        $this->validIdPath = $validIdPath;

        return $this;
    }

    public function getProofOfIncomePath(): string
    {
        return $this->proofOfIncomePath;
    }

    public function setProofOfIncomePath(string $proofOfIncomePath): self
    {
        $this->proofOfIncomePath = $proofOfIncomePath;

        return $this;
    }

    public function getProofOfAddressPath(): string
    {
        return $this->proofOfAddressPath;
    }

    public function setProofOfAddressPath(string $proofOfAddressPath): self
    {
        $this->proofOfAddressPath = $proofOfAddressPath;

        return $this;
    }

    public function getEmploymentCertificatePath(): ?string
    {
        return $this->employmentCertificatePath;
    }

    public function setEmploymentCertificatePath(?string $employmentCertificatePath): self
    {
        $this->employmentCertificatePath = $employmentCertificatePath;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): self
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setLoanApplication($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        $this->payments->removeElement($payment);

        return $this;
    }
}