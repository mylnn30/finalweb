<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\LoanApplicationRepository;
use App\Repository\SystemSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/applications')]
class AdminApplicationController extends AbstractController
{
    #[Route('', name: 'admin_applications', methods: ['GET'])]
    public function index(Request $request, LoanApplicationRepository $applications): Response
    {
        $status = $request->query->get('status');

        return $this->render('admin/applications/index.html.twig', [
            'applications' => $applications->findForAdmin(is_string($status) ? $status : null),
            'status' => $status,
        ]);
    }

    #[Route('/{id}', name: 'admin_application_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, LoanApplicationRepository $applications): Response
    {
        $application = $applications->find($id);
        if (!$application) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/applications/show.html.twig', ['application' => $application]);
    }

    #[Route('/{id}/status', name: 'admin_application_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function updateStatus(int $id, Request $request, LoanApplicationRepository $applications, SystemSettingRepository $settings, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('application_status_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $application = $applications->find($id);
        if (!$application) {
            throw $this->createNotFoundException();
        }
        $status = (string) $request->request->get('status');
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw $this->createNotFoundException('Invalid application status.');
        }

        if ($status === 'approved' && $application->getInterestRate() === null) {
            $defaultRate = $settings->find('default_interest_rate');
            $application->setInterestRate($defaultRate?->getSettingValue() ?? '4.99');
        }
        if ($status === 'approved' && $application->getNextDueDate() === null) {
            $interval = match ($application->getPaymentFrequency()) {
                'weekly' => '+7 days',
                'biweekly' => '+14 days',
                default => '+1 month',
            };
            $application->setNextDueDate((new \DateTimeImmutable('today'))->modify($interval));
        }
        $application->setStatus($status);
        $notification = new Notification();
        $notification->setUser($application->getUser())
            ->setType($status === 'approved' ? 'approval' : ($status === 'rejected' ? 'rejection' : 'application_update'))
            ->setMessage(sprintf('Your %s loan application status is now %s.', str_replace('_', ' ', $application->getLoanType()), $status));
        $entityManager->persist($notification);
        $entityManager->flush();
        $this->addFlash('success', 'Application status updated.');

        return $this->redirectToRoute('admin_application_show', ['id' => $id]);
    }

    #[Route('/{id}/terms', name: 'admin_application_terms', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function updateTerms(int $id, Request $request, LoanApplicationRepository $applications, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('application_terms_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $application = $applications->find($id);
        if (!$application) {
            throw $this->createNotFoundException();
        }
        $rate = filter_var($request->request->get('interest_rate'), FILTER_VALIDATE_FLOAT);
        $due = (string) $request->request->get('next_due_date', '');
        if ($rate === false || $rate < 0 || ($due !== '' && !\DateTimeImmutable::createFromFormat('!Y-m-d', $due))) {
            $this->addFlash('error', 'Enter a valid interest rate and due date.');

            return $this->redirectToRoute('admin_application_show', ['id' => $id]);
        }
        $application->setInterestRate(number_format($rate, 2, '.', ''));
        $application->setNextDueDate($due !== '' ? new \DateTimeImmutable($due) : null);
        $entityManager->flush();

        return $this->redirectToRoute('admin_application_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'admin_application_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, LoanApplicationRepository $applications, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('application_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $application = $applications->find($id);
        if (!$application) {
            throw $this->createNotFoundException();
        }
        if (!$application->getPayments()->isEmpty()) {
            $this->addFlash('error', 'Applications with payment history cannot be deleted.');

            return $this->redirectToRoute('admin_application_show', ['id' => $id]);
        }
        $entityManager->remove($application);
        $entityManager->flush();

        return $this->redirectToRoute('admin_applications');
    }
}