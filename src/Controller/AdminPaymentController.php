<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Payment;
use App\Repository\LoanApplicationRepository;
use App\Repository\PaymentRepository;
use App\Service\LoanFinanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

#[Route('/admin/payments')]
class AdminPaymentController extends AbstractController
{
    #[Route('', name: 'admin_payments', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager, LoanApplicationRepository $applications, PaymentRepository $payments, LoanFinanceService $finance): Response
    {
        $loans = $applications->findForAdmin('approved');
        $choices = [];
        foreach ($loans as $loan) {
            $choices[sprintf('#%d %s · %s', $loan->getId(), $loan->getUser()->getUsername(), $loan->getLoanType())] = (string) $loan->getId();
        }
        $form = $this->createFormBuilder([])
            ->add('loan_id', ChoiceType::class, ['choices' => $choices, 'placeholder' => 'Select loan', 'constraints' => [new Assert\NotBlank()]])
            ->add('payment_date', DateType::class, ['widget' => 'single_text', 'data' => new \DateTimeImmutable('today')])
            ->add('amount_paid', NumberType::class, ['scale' => 2, 'constraints' => [new Assert\Positive()]])
            ->add('payment_method', ChoiceType::class, ['choices' => ['Cash' => 'cash', 'GCash' => 'gcash', 'Bank transfer' => 'bank_transfer']])
            ->add('reference_number', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 100)]])
            ->add('status', ChoiceType::class, ['choices' => ['Confirmed' => 'confirmed', 'Pending' => 'pending']])
            ->add('record', SubmitType::class, ['label' => 'Record payment'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $loan = $applications->find((int) $data['loan_id']);
            $amount = (float) $data['amount_paid'];
            if (!$loan || $loan->getStatus() !== 'approved') {
                $form->get('loan_id')->addError(new FormError('Choose an active approved loan.'));
            } elseif ($amount > $finance->remainingBalance($loan)) {
                $form->get('amount_paid')->addError(new FormError('Payment exceeds remaining balance.'));
            } else {
                $payment = (new Payment())
                    ->setLoanApplication($loan)
                    ->setPaymentDate($data['payment_date'])
                    ->setAmountPaid(number_format($amount, 2, '.', ''))
                    ->setPaymentMethod($data['payment_method'])
                    ->setReferenceNumber(trim($data['reference_number'] ?? '') ?: null)
                    ->setStatus($data['status']);
                $entityManager->persist($payment);
                if ($payment->getStatus() === 'confirmed') {
                    $finance->advanceDueDate($loan);
                } else {
                    $notification = new Notification();
                    $notification->setUser($loan->getUser())->setType('payment_pending')->setMessage('A payment has been recorded and is awaiting confirmation.');
                    $entityManager->persist($notification);
                }
                $entityManager->flush();
                $this->addFlash('success', 'Payment recorded.');

                return $this->redirectToRoute('admin_payments');
            }
        }

        return $this->render('admin/payments/index.html.twig', [
            'form' => $form,
            'payments' => $payments->findForAdmin(),
            'filter' => $request->query->get('status'),
        ]);
    }

    #[Route('/{id}/status', name: 'admin_payment_status', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function updateStatus(int $id, Request $request, PaymentRepository $payments, LoanFinanceService $finance, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('payment_status_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $payment = $payments->find($id);
        if (!$payment) {
            throw $this->createNotFoundException();
        }
        $newStatus = (string) $request->request->get('status');
        if (!in_array($newStatus, ['pending', 'confirmed', 'failed'], true)) {
            throw $this->createNotFoundException('Invalid payment status.');
        }
        if ($payment->getStatus() === 'confirmed' && $newStatus !== 'confirmed') {
            $this->addFlash('error', 'Confirmed payments cannot be changed.');
        } elseif ($newStatus === 'confirmed' && (float) $payment->getAmountPaid() > $finance->remainingBalance($payment->getLoanApplication())) {
            $this->addFlash('error', 'Payment exceeds the remaining balance.');
        } else {
            $wasPending = $payment->getStatus() === 'pending';
            $payment->setStatus($newStatus);
            if ($wasPending && $newStatus === 'confirmed' && (float) $payment->getAmountPaid() > 0) {
                $finance->advanceDueDate($payment->getLoanApplication());
            }
            if ($wasPending && in_array($newStatus, ['confirmed', 'failed'], true)) {
                $notification = new Notification();
                $notification->setUser($payment->getLoanApplication()->getUser())
                    ->setType($newStatus === 'confirmed' ? 'payment_confirmed' : 'payment_rejected')
                    ->setMessage(sprintf('Your payment of PHP %s was %s.', number_format((float) $payment->getAmountPaid(), 2), $newStatus === 'confirmed' ? 'confirmed' : 'rejected'));
                $entityManager->persist($notification);
            }
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_payments');
    }

    #[Route('/{id}/delete', name: 'admin_payment_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, PaymentRepository $payments, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('payment_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $payment = $payments->find($id);
        if (!$payment) {
            throw $this->createNotFoundException();
        }
        if ($payment->getStatus() === 'confirmed') {
            throw $this->createAccessDeniedException('Confirmed payments are immutable.');
        }
        $entityManager->remove($payment);
        $entityManager->flush();

        return $this->redirectToRoute('admin_payments');
    }
}