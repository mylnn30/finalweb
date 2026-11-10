<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\User;
use App\Repository\LoanApplicationRepository;
use App\Repository\PaymentRepository;
use App\Service\LoanFinanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentController extends AbstractBorrowerController
{
    #[Route('/payments', name: 'app_payments', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        LoanApplicationRepository $applications,
        PaymentRepository $payments,
        LoanFinanceService $finance,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        /** @var User $user */
        $user = $this->getUser();
        $loans = array_values(array_filter($applications->findForUser($user), static fn ($loan) => $loan->getStatus() === 'approved'));
        $choices = [];
        foreach ($loans as $loan) {
            $choices[sprintf('#%d %s (balance PHP %s)', $loan->getId(), ucfirst(str_replace('_', ' ', $loan->getLoanType())), number_format($finance->remainingBalance($loan), 2))] = (string) $loan->getId();
        }
        $form = $this->createFormBuilder([])
            ->add('loan_id', ChoiceType::class, ['choices' => $choices, 'placeholder' => 'Select an approved loan', 'constraints' => [new Assert\NotBlank()]])
            ->add('payment_date', DateType::class, ['widget' => 'single_text', 'input' => 'datetime_immutable', 'data' => new \DateTimeImmutable('today')])
            ->add('amount_paid', NumberType::class, ['scale' => 2, 'constraints' => [new Assert\Positive()]])
            ->add('payment_method', ChoiceType::class, ['choices' => ['Cash' => 'cash', 'GCash' => 'gcash', 'Bank transfer' => 'bank_transfer']])
            ->add('reference_number', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 100)]])
            ->add('submit', SubmitType::class, ['label' => 'Submit payment'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $loan = $applications->findOneBy(['id' => (int) $data['loan_id'], 'user' => $user, 'status' => 'approved']);
            $amount = (float) $data['amount_paid'];
            if (!$loan) {
                $form->get('loan_id')->addError(new FormError('Select one of your active loans.'));
            } elseif ($amount > $finance->remainingBalance($loan)) {
                $form->get('amount_paid')->addError(new FormError('Payment cannot exceed the remaining balance.'));
            } else {
                $payment = (new Payment())
                    ->setLoanApplication($loan)
                    ->setPaymentDate($data['payment_date'])
                    ->setAmountPaid(number_format($amount, 2, '.', ''))
                    ->setPaymentMethod($data['payment_method'])
                    ->setReferenceNumber(trim($data['reference_number'] ?? '') ?: null)
                    ->setStatus('pending');
                $entityManager->persist($payment);
                $notification = new \App\Entity\Notification();
                $notification->setUser($user)->setType('payment_pending')->setMessage('Your payment has been submitted and is awaiting confirmation.');
                $entityManager->persist($notification);
                $entityManager->flush();
                $this->addFlash('success', 'Payment submitted for confirmation.');

                return $this->redirectToRoute('app_payments');
            }
        }

        return $this->render('payments/index.html.twig', [
            'form' => $form,
            'loans' => $loans,
            'payments' => $payments->findForUser($user),
            'finance' => $finance,
        ]);
    }
}