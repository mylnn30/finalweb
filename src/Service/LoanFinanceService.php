<?php

namespace App\Service;

use App\Entity\LoanApplication;
use App\Repository\PaymentRepository;

class LoanFinanceService
{
    public function __construct(private readonly PaymentRepository $payments)
    {
    }

    public function totalPayable(LoanApplication $loan): float
    {
        $amount = max(0.0, (float) $loan->getAmount());
        $rate = max(0.0, (float) ($loan->getInterestRate() ?? '0'));

        return round($amount + ($amount * $rate / 100), 2);
    }

    public function remainingBalance(LoanApplication $loan): float
    {
        return max(0.0, round($this->totalPayable($loan) - (float) $this->payments->sumConfirmedForLoan((int) $loan->getId()), 2));
    }

    public function installmentAmount(LoanApplication $loan): float
    {
        $installments = match ($loan->getPaymentFrequency()) {
            'weekly' => $loan->getTermMonths() * 4,
            'biweekly' => $loan->getTermMonths() * 2,
            default => $loan->getTermMonths(),
        };

        return $installments > 0 ? round($this->totalPayable($loan) / $installments, 2) : 0.0;
    }

    public function isOverdue(LoanApplication $loan): bool
    {
        return $loan->getNextDueDate() !== null
            && $loan->getNextDueDate() < new \DateTimeImmutable('today')
            && $this->remainingBalance($loan) > 0;
    }

    public function advanceDueDate(LoanApplication $loan): void
    {
        if ($loan->getNextDueDate() === null) {
            return;
        }

        $interval = match ($loan->getPaymentFrequency()) {
            'weekly' => '+7 days',
            'biweekly' => '+14 days',
            default => '+1 month',
        };
        $loan->setNextDueDate($loan->getNextDueDate()->modify($interval));
    }
}