<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
#[ORM\Index(name: 'idx_payments_date', columns: ['payment_date'])]
#[ORM\Index(name: 'idx_payments_loan_status', columns: ['loan_id', 'status'])]
#[ORM\UniqueConstraint(name: 'uq_payments_reference', columns: ['reference_number'])]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LoanApplication::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(name: 'loan_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private LoanApplication $loanApplication;

    #[ORM\Column(name: 'payment_date', type: 'date_immutable')]
    private \DateTimeImmutable $paymentDate;

    #[ORM\Column(name: 'amount_paid', type: 'decimal', precision: 12, scale: 2)]
    private string $amountPaid;

    #[ORM\Column(name: 'payment_method', type: 'string', length: 20)]
    private string $paymentMethod;

    #[ORM\Column(name: 'reference_number', type: 'string', length: 100, nullable: true)]
    private ?string $referenceNumber = null;

    #[ORM\Column(name: 'proof_of_payment_path', type: 'string', length: 255, nullable: true)]
    private ?string $proofOfPaymentPath = null;

    #[ORM\Column(name: 'status', type: 'string', length: 20, options: ['default' => 'confirmed'])]
    private string $status = 'confirmed';

    #[ORM\Column(name: 'recorded_at', type: 'datetime_immutable', columnDefinition: 'timestamp NOT NULL DEFAULT current_timestamp()')]
    private \DateTimeImmutable $recordedAt;

    public function __construct()
    {
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanApplication(): LoanApplication
    {
        return $this->loanApplication;
    }

    public function setLoanApplication(LoanApplication $loanApplication): self
    {
        $this->loanApplication = $loanApplication;

        return $this;
    }

    public function getPaymentDate(): \DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeImmutable $paymentDate): self
    {
        $this->paymentDate = $paymentDate;

        return $this;
    }

    public function getAmountPaid(): string
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(string $amountPaid): self
    {
        $this->amountPaid = $amountPaid;

        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    public function getProofOfPaymentPath(): ?string
    {
        return $this->proofOfPaymentPath;
    }

    public function setProofOfPaymentPath(?string $proofOfPaymentPath): self
    {
        $this->proofOfPaymentPath = $proofOfPaymentPath;

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

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeImmutable $recordedAt): self
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }
}