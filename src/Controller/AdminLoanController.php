<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Payment;
use App\Repository\LoanApplicationRepository;
use App\Repository\SystemSettingRepository;
use App\Service\LoanFinanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/loans')]
#[IsGranted('ROLE_ADMIN')]
class AdminLoanController extends AbstractController
{
    #[Route('', name: 'admin_loans', methods: ['GET'])]
    public function index(Request $request, LoanApplicationRepository $applications, LoanFinanceService $finance): Response
    {
        $filter = (string) $request->query->get('filter', 'all');
        $today = new \DateTimeImmutable('today');
        $loans = [];
        foreach ($applications->findForAdmin('approved') as $loan) {
            $balance = $finance->remainingBalance($loan);
            if ($balance <= 0) {
                continue;
            }
            $overdue = $finance->isOverdue($loan);
            $upcoming = $loan->getNextDueDate() !== null && $loan->getNextDueDate() >= $today && $loan->getNextDueDate() <= $today->modify('+7 days');
            if (($filter === 'overdue' && !$overdue) || ($filter === 'upcoming' && !$upcoming)) {
                continue;
            }
            $loans[] = ['loan' => $loan, 'balance' => $balance, 'overdue' => $overdue, 'installment' => $finance->installmentAmount($loan)];
        }

        return $this->render('admin/loans/index.html.twig', ['loans' => $loans, 'filter' => $filter]);
    }

    #[Route('/{id}/action', name: 'admin_loan_action', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function action(int $id, Request $request, LoanApplicationRepository $applications, SystemSettingRepository $settings, LoanFinanceService $finance, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('loan_action_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $loan = $applications->find($id);
        if (!$loan || $loan->getStatus() !== 'approved') {
            throw $this->createNotFoundException();
        }
        $action = (string) $request->request->get('action');
        if (!in_array($action, ['remind', 'notify_overdue', 'apply_penalty'], true)) {
            throw $this->createNotFoundException('Unknown loan action.');
        }

        if ($action === 'apply_penalty') {
            if (!$finance->isOverdue($loan)) {
                throw $this->createAccessDeniedException('Late fees can only be applied to overdue loans.');
            }
            $setting = $settings->find('late_payment_penalty_percent');
            $percent = (float) ($setting?->getSettingValue() ?? '2.00');
            $fee = round($finance->installmentAmount($loan) * $percent / 100, 2);
            if ($fee <= 0) {
                $this->addFlash('error', 'The configured late fee could not be calculated.');
            } else {
                $payment = (new Payment())
                    ->setLoanApplication($loan)
                    ->setPaymentDate(new \DateTimeImmutable('today'))
                    ->setAmountPaid(number_format(-$fee, 2, '.', ''))
                    ->setPaymentMethod('cash')
                    ->setReferenceNumber(sprintf('Late fee %d %s', $id, bin2hex(random_bytes(5))))
                    ->setStatus('confirmed');
                $entityManager->persist($payment);
                $this->notify($entityManager, $loan, 'late_fee', sprintf('A late fee of PHP %s was added to your loan.', number_format($fee, 2)));
                $this->addFlash('success', 'Late fee applied.');
            }
        } else {
            $type = $action === 'remind' ? 'payment_reminder' : 'overdue';
            $message = $action === 'remind'
                ? 'A payment on your loan is coming due.'
                : 'Your loan payment is overdue. Please settle it as soon as possible.';
            $this->notify($entityManager, $loan, $type, $message);
            $this->addFlash('success', 'Borrower notified.');
        }
        $entityManager->flush();

        return $this->redirectToRoute('admin_loans', ['filter' => $request->query->get('filter', 'all')]);
    }

    private function notify(EntityManagerInterface $entityManager, \App\Entity\LoanApplication $loan, string $type, string $message): void
    {
        $notification = new Notification();
        $notification->setUser($loan->getUser())->setType($type)->setMessage($message);
        $entityManager->persist($notification);
    }
}