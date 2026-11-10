<?php

namespace App\Controller;

use App\Entity\LoanApplication;
use App\Entity\Payment;
use App\Entity\User;
use App\Repository\LoanApplicationRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Service\LoanFinanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class AdminReportController extends AbstractController
{
    #[Route('', name: 'admin_reports', methods: ['GET'])]
    public function index(Request $request, LoanApplicationRepository $applications, PaymentRepository $payments, UserRepository $users, LoanFinanceService $finance, EntityManagerInterface $entityManager): Response
    {
        $type = (string) $request->query->get('type', 'loans');
        if (!in_array($type, ['loans', 'payments', 'borrowers', 'overdue', 'summary'], true)) {
            $type = 'loans';
        }
        $year = max(2000, (int) $request->query->get('year', date('Y')));
        $month = min(12, max(1, (int) $request->query->get('month', date('n'))));
        $rows = match ($type) {
            'payments' => $payments->findForAdmin(),
            'borrowers' => $users->searchUsers(),
            'overdue' => array_values(array_filter($applications->findForAdmin('approved'), fn ($loan) => $finance->isOverdue($loan))),
            default => $applications->findForAdmin('approved'),
        };
        $summary = null;
        if ($type === 'summary') {
            $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
            $end = $start->modify('+1 month');
            $summary = [
                'newApplications' => (int) $entityManager->createQueryBuilder()->select('COUNT(application.id)')->from(LoanApplication::class, 'application')->andWhere('application.submittedAt >= :start AND application.submittedAt < :end')->setParameter('start', $start)->setParameter('end', $end)->getQuery()->getSingleScalarResult(),
                'approvedAmount' => (float) $entityManager->createQueryBuilder()->select('COALESCE(SUM(application.amount), 0)')->from(LoanApplication::class, 'application')->andWhere('application.status = :status')->andWhere('application.submittedAt >= :start AND application.submittedAt < :end')->setParameter('status', 'approved')->setParameter('start', $start)->setParameter('end', $end)->getQuery()->getSingleScalarResult(),
                'collected' => (float) $entityManager->createQueryBuilder()->select('COALESCE(SUM(payment.amountPaid), 0)')->from(Payment::class, 'payment')->andWhere('payment.status = :status')->andWhere('payment.paymentDate >= :startDate AND payment.paymentDate < :endDate')->setParameter('status', 'confirmed')->setParameter('startDate', $start->format('Y-m-d'))->setParameter('endDate', $end->format('Y-m-d'))->getQuery()->getSingleScalarResult(),
            ];
        }

        return $this->render('admin/reports/index.html.twig', compact('type', 'year', 'month', 'rows', 'summary', 'finance'));
    }

    #[Route('/export.csv', name: 'admin_report_export', methods: ['GET'])]
    public function export(Request $request, LoanApplicationRepository $applications, PaymentRepository $payments, UserRepository $users, LoanFinanceService $finance): Response
    {
        $type = (string) $request->query->get('type', 'loans');
        if (!in_array($type, ['loans', 'payments', 'borrowers', 'overdue'], true)) {
            throw $this->createNotFoundException('Unknown report type.');
        }
        $rows = match ($type) {
            'payments' => $payments->findForAdmin(),
            'borrowers' => $users->searchUsers(),
            'overdue' => array_values(array_filter($applications->findForAdmin('approved'), fn ($loan) => $finance->isOverdue($loan))),
            default => $applications->findForAdmin('approved'),
        };
        $stream = fopen('php://temp', 'r+');
        if ($type === 'borrowers') {
            fputcsv($stream, ['User ID', 'Username', 'Name', 'Email', 'Phone', 'Role', 'Status']);
            foreach ($rows as $user) {
                fputcsv($stream, [$user->getId(), $user->getUsername(), $user->getFirstName() . ' ' . $user->getLastName(), $user->getEmail(), $user->getPhoneNumber(), $user->isAdmin() ? 'Admin' : ($user->isStaff() ? 'Staff' : 'Borrower'), $user->isActive() ? 'Active' : 'Deactivated']);
            }
        } elseif ($type === 'payments') {
            fputcsv($stream, ['Payment ID', 'Loan ID', 'Borrower', 'Date', 'Amount', 'Method', 'Reference', 'Status']);
            foreach ($rows as $payment) {
                fputcsv($stream, [$payment->getId(), $payment->getLoanApplication()->getId(), $payment->getLoanApplication()->getUser()->getUsername(), $payment->getPaymentDate()->format('Y-m-d'), $payment->getAmountPaid(), $payment->getPaymentMethod(), $payment->getReferenceNumber(), $payment->getStatus()]);
            }
        } else {
            fputcsv($stream, ['Loan ID', 'Borrower', 'Loan Type', 'Amount', 'Remaining Balance', 'Next Due', 'Status']);
            foreach ($rows as $loan) {
                fputcsv($stream, [$loan->getId(), $loan->getUser()->getUsername(), $loan->getLoanType(), $loan->getAmount(), number_format($finance->remainingBalance($loan), 2, '.', ''), $loan->getNextDueDate()?->format('Y-m-d'), $loan->getStatus()]);
            }
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return new Response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="mletchido_%s_%s.csv"', $type, date('Y-m-d')),
        ]);
    }
}