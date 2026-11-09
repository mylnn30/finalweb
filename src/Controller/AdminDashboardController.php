<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\LoanApplicationRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Service\LoanFinanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        UserRepository $users,
        LoanApplicationRepository $applications,
        PaymentRepository $payments,
        LoanFinanceService $finance,
    ): Response {
        $activeLoans = array_values(array_filter($applications->findForAdmin('approved'), fn ($loan) => $finance->remainingBalance($loan) > 0));
        $overdue = count(array_filter($activeLoans, fn ($loan) => $finance->isOverdue($loan)));
        $collected = (float) $payments->createQueryBuilder('payment')
            ->select('COALESCE(SUM(payment.amountPaid), 0)')
            ->andWhere('payment.status = :status')
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [
            'borrowers' => $users->count(['isAdmin' => false]),
            'pending' => $applications->countByStatus('pending'),
            'approved' => $applications->countByStatus('approved'),
            'rejected' => $applications->countByStatus('rejected'),
            'activeLoans' => count($activeLoans),
            'overdue' => $overdue,
            'collected' => $collected,
        ]);
    }
}