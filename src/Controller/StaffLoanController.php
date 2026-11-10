<?php

namespace App\Controller;

use App\Entity\LoanApplication;
use App\Entity\User;
use App\Form\LoanApplicationType;
use App\Repository\LoanApplicationRepository;
use App\Repository\UserRepository;
use App\Service\DocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffLoanController extends AbstractController
{
    private const LOAN_TERMS = [
        'home' => [60, 120, 180, 240, 300, 360],
        'business' => [12, 24, 36, 48, 60, 72, 84],
        'personal' => [12, 18, 24, 36, 48, 60],
        'asset_backed' => [24, 36, 48, 60, 72, 84, 96, 108, 120],
    ];

    #[Route('', name: 'staff_dashboard', methods: ['GET'])]
    public function dashboard(LoanApplicationRepository $applications): Response
    {
        /** @var User $staff */
        $staff = $this->getUser();
        $assigned = $applications->findForAssignedStaff($staff);

        return $this->render('staff/dashboard.html.twig', [
            'applications' => $assigned,
            'pending' => count(array_filter($assigned, static fn (LoanApplication $application): bool => $application->getStatus() === 'pending')),
        ]);
    }

    #[Route('/applications', name: 'staff_applications', methods: ['GET'])]
    public function index(Request $request, LoanApplicationRepository $applications): Response
    {
        /** @var User $staff */
        $staff = $this->getUser();

        return $this->render('staff/applications/index.html.twig', [
            'applications' => $applications->findForAssignedStaff(
                $staff,
                $request->query->get('status'),
                (string) $request->query->get('q', ''),
            ),
            'query' => (string) $request->query->get('q', ''),
            'status' => $request->query->get('status'),
        ]);
    }

    #[Route('/applications/new', name: 'staff_application_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, DocumentStorageService $storage): Response
    {
        /** @var User $staff */
        $staff = $this->getUser();
        $application = new LoanApplication();
        $form = $this->createForm(LoanApplicationType::class, $application, [
            'include_borrower' => true,
            'require_documents' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->hasValidEmploymentDetails($application, $form)) {
                return $this->render('staff/applications/form.html.twig', ['form' => $form, 'isNew' => true]);
            }

            try {
                $borrower = $form->get('user')->getData();
                if (!$borrower instanceof User) {
                    $form->get('user')->addError(new FormError('Select a borrower account.'));

                    return $this->render('staff/applications/form.html.twig', ['form' => $form, 'isNew' => true]);
                }
                $application->setUser($borrower);
                $this->storeDocuments($application, $form, $storage, true);
                $application->setAssignedStaff($staff)->setStatus('pending');
                $entityManager->persist($application);
                $entityManager->flush();
                $this->addFlash('success', 'Loan application created and assigned to you.');

                return $this->redirectToRoute('staff_applications');
            } catch (\RuntimeException $exception) {
                $form->addError(new FormError('The documents could not be saved. Please try again.'));
            }
        }

        return $this->render('staff/applications/form.html.twig', ['form' => $form, 'isNew' => true]);
    }

    #[Route('/applications/{id}', name: 'staff_application_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, LoanApplicationRepository $applications): Response
    {
        $application = $this->findAssigned($id, $applications);

        return $this->render('staff/applications/show.html.twig', ['application' => $application]);
    }

    #[Route('/applications/{id}/edit', name: 'staff_application_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, LoanApplicationRepository $applications, EntityManagerInterface $entityManager, DocumentStorageService $storage): Response
    {
        $application = $this->findAssigned($id, $applications);
        $form = $this->createForm(LoanApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->hasValidEmploymentDetails($application, $form)) {
                return $this->render('staff/applications/form.html.twig', ['form' => $form, 'isNew' => false]);
            }

            try {
                $this->storeDocuments($application, $form, $storage, false);
                $entityManager->flush();
                $this->addFlash('success', 'Assigned application updated.');

                return $this->redirectToRoute('staff_application_show', ['id' => $id]);
            } catch (\RuntimeException $exception) {
                $form->addError(new FormError('The documents could not be saved. Please try again.'));
            }
        }

        return $this->render('staff/applications/form.html.twig', ['form' => $form, 'isNew' => false]);
    }

    #[Route('/applications/{id}/delete', name: 'staff_application_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, LoanApplicationRepository $applications, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('staff_application_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $application = $this->findAssigned($id, $applications);
        if (!$application->getPayments()->isEmpty()) {
            $this->addFlash('error', 'Applications with payment history cannot be deleted.');

            return $this->redirectToRoute('staff_application_show', ['id' => $id]);
        }

        $entityManager->remove($application);
        $entityManager->flush();
        $this->addFlash('success', 'Assigned application deleted.');

        return $this->redirectToRoute('staff_applications');
    }

    private function findAssigned(int $id, LoanApplicationRepository $applications): LoanApplication
    {
        /** @var User $staff */
        $staff = $this->getUser();
        $application = $applications->findOneBy(['id' => $id, 'assignedStaff' => $staff]);
        if (!$application) {
            throw $this->createNotFoundException();
        }

        return $application;
    }

    private function hasValidEmploymentDetails(LoanApplication $application, \Symfony\Component\Form\FormInterface $form): bool
    {
        $valid = isset(self::LOAN_TERMS[$application->getLoanType()])
            && in_array($application->getTermMonths(), self::LOAN_TERMS[$application->getLoanType()], true);
        if (!$valid) {
            $form->get('termMonths')->addError(new FormError('Select a valid term for this loan type.'));

            return false;
        }

        if ($application->getEmploymentStatus() === 'employed'
            && (!$application->getEmployerName() || !$application->getLengthOfEmployment() || !$application->getEmployerContactNumber()
                || (!$application->getEmploymentCertificatePath() && !($form->get('employmentCertificatePath')->getData() instanceof UploadedFile)))
        ) {
            $form->addError(new FormError('Employed applicants must provide employer details and an employment certificate.'));

            return false;
        }
        if ($application->getEmploymentStatus() === 'self_employed' && (!$application->getBusinessName() || !$application->getBusinessType())) {
            $form->addError(new FormError('Self-employed applicants must provide a business name and type.'));

            return false;
        }

        return true;
    }

    private function storeDocuments(LoanApplication $application, \Symfony\Component\Form\FormInterface $form, DocumentStorageService $storage, bool $required): void
    {
        foreach ([
            'validIdPath' => 'setValidIdPath',
            'proofOfIncomePath' => 'setProofOfIncomePath',
            'proofOfAddressPath' => 'setProofOfAddressPath',
            'employmentCertificatePath' => 'setEmploymentCertificatePath',
        ] as $field => $setter) {
            $file = $form->get($field)->getData();
            if ($file instanceof UploadedFile) {
                $application->{$setter}($storage->store($file));
            } elseif ($required && $field !== 'employmentCertificatePath') {
                throw new \RuntimeException('A required application document is missing.');
            }
        }
    }
}
