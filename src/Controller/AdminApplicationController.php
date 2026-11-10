<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Form\LoanApplicationType;
use App\Service\DocumentStorageService;
use App\Repository\LoanApplicationRepository;
use App\Repository\SystemSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/applications')]
#[IsGranted('ROLE_ADMIN')]
class AdminApplicationController extends AbstractController
{
    #[Route('', name: 'admin_applications', methods: ['GET'])]
    public function index(Request $request, LoanApplicationRepository $applications): Response
    {
        $status = $request->query->get('status');
        $search = (string) $request->query->get('q', '');

        return $this->render('admin/applications/index.html.twig', [
            'applications' => $applications->findForAdmin(is_string($status) ? $status : null, $search),
            'status' => $status,
            'query' => $search,
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

    #[Route('/{id}/edit', name: 'admin_application_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, LoanApplicationRepository $applications, EntityManagerInterface $entityManager, DocumentStorageService $storage): Response
    {
        $application = $applications->find($id);
        if (!$application) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(LoanApplicationType::class, $application, [
            'include_borrower' => true,
            'include_staff' => true,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $terms = [
                'home' => [60, 120, 180, 240, 300, 360],
                'business' => [12, 24, 36, 48, 60, 72, 84],
                'personal' => [12, 18, 24, 36, 48, 60],
                'asset_backed' => [24, 36, 48, 60, 72, 84, 96, 108, 120],
            ];
            if (!isset($terms[$application->getLoanType()]) || !in_array($application->getTermMonths(), $terms[$application->getLoanType()], true)) {
                $form->get('termMonths')->addError(new FormError('Select a valid term for this loan type.'));
            } elseif ($application->getEmploymentStatus() === 'employed'
                && (!$application->getEmployerName() || !$application->getLengthOfEmployment() || !$application->getEmployerContactNumber()
                    || (!$application->getEmploymentCertificatePath() && !($form->get('employmentCertificatePath')->getData() instanceof UploadedFile)))
            ) {
                $form->addError(new FormError('Employed applicants must provide employer details and an employment certificate.'));
            } elseif ($application->getEmploymentStatus() === 'self_employed' && (!$application->getBusinessName() || !$application->getBusinessType())) {
                $form->addError(new FormError('Self-employed applicants must provide a business name and type.'));
            } else {
                try {
                    foreach ([
                        'validIdPath' => 'setValidIdPath',
                        'proofOfIncomePath' => 'setProofOfIncomePath',
                        'proofOfAddressPath' => 'setProofOfAddressPath',
                        'employmentCertificatePath' => 'setEmploymentCertificatePath',
                    ] as $field => $setter) {
                        $file = $form->get($field)->getData();
                        if ($file instanceof UploadedFile) {
                            $application->{$setter}($storage->store($file));
                        }
                    }
                    $borrower = $form->get('user')->getData();
                    if ($borrower instanceof \App\Entity\User) {
                        $application->setUser($borrower);
                    }
                    $entityManager->flush();
                    $this->addFlash('success', 'Application updated.');

                    return $this->redirectToRoute('admin_application_show', ['id' => $id]);
                } catch (\RuntimeException $exception) {
                    $form->addError(new FormError('The documents could not be saved. Please try again.'));
                }
            }
        }

        return $this->render('admin/applications/edit.html.twig', ['form' => $form, 'application' => $application]);
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