<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LoanApplicationRepository;
use App\Repository\NotificationRepository;
use App\Service\LoanFinanceService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractBorrowerController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        LoanApplicationRepository $applications,
        NotificationRepository $notifications,
        LoanFinanceService $finance,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        /** @var User $user */
        $user = $this->getUser();
        $allApplications = $applications->findForUser($user);
        $activeLoans = array_values(array_filter($allApplications, static fn ($loan) => $loan->getStatus() === 'approved'));
        $remaining = 0.0;
        foreach ($activeLoans as $loan) {
            $remaining += $finance->remainingBalance($loan);
        }

        return $this->render('dashboard/index.html.twig', [
            'applications' => array_slice($allApplications, 0, 3),
            'activeLoans' => $activeLoans,
            'remaining' => $remaining,
            'unreadCount' => $notifications->countUnreadForUser($user),
            'unreadNotifications' => $notifications->findRecentForUser($user, true),
            'finance' => $finance,
        ]);
    }
}