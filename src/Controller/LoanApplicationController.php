<?php

namespace App\Controller;

use App\Entity\LoanApplication;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\LoanApplicationRepository;
use App\Service\DocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/applications')]
class LoanApplicationController extends AbstractBorrowerController
{
    private const LOAN_TERMS = [
        'home' => [60, 120, 180, 240, 300, 360],
        'business' => [12, 24, 36, 48, 60, 72, 84],
        'personal' => [12, 18, 24, 36, 48, 60],
        'asset_backed' => [24, 36, 48, 60, 72, 84, 96, 108, 120],
    ];

    #[Route('', name: 'app_applications', methods: ['GET'])]
    public function index(LoanApplicationRepository $applications): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('applications/index.html.twig', ['applications' => $applications->findForUser($this->getUser())]);
    }

    #[Route('/new', name: 'app_loan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, DocumentStorageService $storage, LoggerInterface $logger): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $application = new LoanApplication();
        $form = $this->createFormBuilder([])
            ->add('loan_type', ChoiceType::class, ['choices' => ['Home Loan' => 'home', 'Business Loan' => 'business', 'Personal Loan' => 'personal', 'Asset-Backed Loan' => 'asset_backed'], 'expanded' => true, 'constraints' => [new Assert\NotBlank()]])
            ->add('id_type', ChoiceType::class, ['choices' => ['Philippine Passport' => 'philippine_passport', "Driver's License" => 'drivers_license', 'SSS ID' => 'sss_id', 'UMID' => 'umid', 'PhilHealth ID' => 'philhealth_id', 'Postal ID' => 'postal_id', "Voter's ID" => 'voters_id', 'National ID (PhilSys)' => 'philsys_id', 'TIN ID' => 'tin_id'], 'constraints' => [new Assert\NotBlank()]])
            ->add('employment_status', ChoiceType::class, ['choices' => ['Employed' => 'employed', 'Self-employed' => 'self_employed'], 'constraints' => [new Assert\NotBlank()]])
            ->add('occupation', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)]])
            ->add('employer_name', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('length_of_employment', ChoiceType::class, ['required' => false, 'placeholder' => 'Select', 'choices' => ['Less than 1 year' => 'less_than_1_year', '1-2 years' => '1_to_2_years', '3-5 years' => '3_to_5_years', '6-10 years' => '6_to_10_years', 'More than 10 years' => 'more_than_10_years']])
            ->add('employer_contact_number', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 20), new Assert\Regex('/^\+?[0-9\s\-()]*$/')]])
            ->add('business_name', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 150)]])
            ->add('business_type', ChoiceType::class, ['required' => false, 'placeholder' => 'Select', 'choices' => ['Sole Proprietorship' => 'sole_proprietorship', 'Retail / Sari-Sari Store' => 'retail_sari_sari_store', 'Food & Beverage' => 'food_and_beverage', 'Services' => 'services', 'Online Business' => 'online_business', 'Other' => 'other']])
            ->add('monthly_income', NumberType::class, ['scale' => 2, 'constraints' => [new Assert\Positive(), new Assert\LessThanOrEqual(10000000)]])
            ->add('payment_frequency', ChoiceType::class, ['choices' => ['Monthly' => 'monthly', 'Bi-weekly' => 'biweekly', 'Weekly' => 'weekly']])
            ->add('amount', NumberType::class, ['scale' => 2, 'constraints' => [new Assert\Range(min: 5000, max: 50000000)]])
            ->add('term', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(static fn (int $months): string => $months . ' Months', array_unique(array_merge(...array_values(self::LOAN_TERMS)))),
                    array_unique(array_merge(...array_values(self::LOAN_TERMS))),
                ),
                'placeholder' => 'Select a term',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('purpose', TextareaType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 500)]])
            ->add('valid_id', FileType::class, ['mapped' => false, 'constraints' => [new Assert\NotNull(), new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png'])]])
            ->add('proof_of_income', FileType::class, ['mapped' => false, 'constraints' => [new Assert\NotNull(), new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png'])]])
            ->add('proof_of_address', FileType::class, ['mapped' => false, 'constraints' => [new Assert\NotNull(), new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png'])]])
            ->add('employment_certificate', FileType::class, ['mapped' => false, 'required' => false, 'constraints' => [new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png'])]])
            ->add('submit', SubmitType::class, ['label' => 'Submit application'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $status = $data['employment_status'];
            $formValid = isset(self::LOAN_TERMS[$data['loan_type']]) && in_array((int) $data['term'], self::LOAN_TERMS[$data['loan_type']], true);
            if (!$formValid) {
                $form->get('term')->addError(new FormError('Select a valid term for this loan type.'));
            } elseif (($status === 'employed' && (!$data['employer_name'] || !$data['length_of_employment'] || !$data['employer_contact_number'] || !$form->get('employment_certificate')->getData()))) {
                $form->addError(new FormError('Employed applicants must provide employer details and an employment certificate.'));
            } elseif (($status === 'self_employed' && (!$data['business_name'] || !$data['business_type']))) {
                $form->addError(new FormError('Self-employed applicants must provide their business name and type.'));
            } else {
                try {
                    $user = $this->getUser();
                    if (!$user instanceof User) {
                        throw new \LogicException('A borrower must be authenticated to submit an application.');
                    }

                    $application
                        ->setUser($user)
                        ->setLoanType($data['loan_type'])
                        ->setIdType($data['id_type'])
                        ->setEmploymentStatus($status)
                        ->setOccupation(trim($data['occupation']))
                        ->setEmployerName($status === 'employed' ? trim($data['employer_name']) : null)
                        ->setLengthOfEmployment($status === 'employed' ? $data['length_of_employment'] : null)
                        ->setEmployerContactNumber($status === 'employed' ? trim($data['employer_contact_number']) : null)
                        ->setBusinessName($status === 'self_employed' ? trim($data['business_name']) : null)
                        ->setBusinessType($status === 'self_employed' ? $data['business_type'] : null)
                        ->setMonthlyIncome(number_format((float) $data['monthly_income'], 2, '.', ''))
                        ->setPaymentFrequency($data['payment_frequency'])
                        ->setAmount(number_format((float) $data['amount'], 2, '.', ''))
                        ->setTermMonths((int) $data['term'])
                        ->setPurpose(trim($data['purpose']))
                        ->setValidIdPath($storage->store($form->get('valid_id')->getData()))
                        ->setProofOfIncomePath($storage->store($form->get('proof_of_income')->getData()))
                        ->setProofOfAddressPath($storage->store($form->get('proof_of_address')->getData()));
                    if ($form->get('employment_certificate')->getData()) {
                        $application->setEmploymentCertificatePath($storage->store($form->get('employment_certificate')->getData()));
                    }
                    $entityManager->persist($application);
                    $entityManager->persist((new Notification())
                        ->setUser($user)
                        ->setType('application_update')
                        ->setMessage('Your loan application has been submitted and is pending review.'));
                    $entityManager->flush();

                    $this->addFlash('success', sprintf('Application #%d submitted successfully. You can track it in My Applications.', $application->getId()));

                    return $this->redirectToRoute('app_applications');
                } catch (\Throwable $exception) {
                    $logger->error('Loan application submission failed.', [
                        'exception' => $exception,
                        'user_id' => $this->getUser() instanceof User ? $this->getUser()->getId() : null,
                    ]);
                    $form->addError(new FormError('We could not confirm your application was submitted. Please try again, or contact support if this keeps happening.'));
                }
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Your application was not submitted. Please correct the highlighted fields and submit again.');
        }

        return $this->render('applications/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_application_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, LoanApplicationRepository $applications): Response
    {
        if ($redirect = $this->redirectAdmins()) {
            return $redirect;
        }

        $application = $applications->findOneBy(['id' => $id, 'user' => $this->getUser()]);
        if (!$application) {
            throw $this->createNotFoundException();
        }

        return $this->render('applications/show.html.twig', ['application' => $application]);
    }
}