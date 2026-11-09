<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EligibilityController extends AbstractController
{
    private const LOAN_TERMS = [
        'home' => [60, 120, 180, 240, 300, 360],
        'business' => [12, 24, 36, 48, 60, 72, 84],
        'personal' => [12, 18, 24, 36, 48, 60],
        'asset_backed' => [24, 36, 48, 60, 72, 84, 96, 108, 120],
    ];

    #[Route('/eligibility', name: 'app_eligibility', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $data = [
            'age' => (int) $request->request->get('age', 30),
            'employment_status' => (string) $request->request->get('employment_status', ''),
            'monthly_income' => (float) $request->request->get('monthly_income', 25000),
            'loan_type' => (string) $request->request->get('loan_type', 'personal'),
            'amount' => (float) $request->request->get('amount', 100000),
            'term' => (int) $request->request->get('term', 12),
        ];
        $result = null;
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!in_array($data['employment_status'], ['employed', 'self_employed'], true)) {
                $errors[] = 'Select a valid employment status.';
            }
            if (!isset(self::LOAN_TERMS[$data['loan_type']])) {
                $errors[] = 'Select a valid loan type.';
            } elseif (!in_array($data['term'], self::LOAN_TERMS[$data['loan_type']], true)) {
                $errors[] = 'Select a valid term for this loan type.';
            }
            if ($data['age'] < 1 || $data['monthly_income'] <= 0 || $data['amount'] <= 0) {
                $errors[] = 'Enter valid age, income, and loan amount values.';
            }

            if ($errors === []) {
                $allPass = $data['age'] >= 18 && $data['age'] <= 70
                    && $data['monthly_income'] >= 8000
                    && $data['amount'] >= 5000 && $data['amount'] <= 50000000;
                $monthlyRate = 0.0499 / 12;
                $payment = $data['amount'] * ($monthlyRate * (1 + $monthlyRate) ** $data['term'])
                    / ((1 + $monthlyRate) ** $data['term'] - 1);

                $result = [
                    'eligible' => $allPass,
                    'payment' => $payment,
                    'checks' => [
                        ['label' => 'Age is between 18 and 70', 'pass' => $data['age'] >= 18 && $data['age'] <= 70],
                        ['label' => 'Employment or self-employment income source', 'pass' => true],
                        ['label' => 'Monthly income is at least PHP 8,000', 'pass' => $data['monthly_income'] >= 8000],
                        ['label' => 'Loan amount is within PHP 5,000 to PHP 50,000,000', 'pass' => $data['amount'] >= 5000 && $data['amount'] <= 50000000],
                    ],
                ];
            }
        }

        return $this->render('site/eligibility.html.twig', [
            'data' => $data,
            'result' => $result,
            'errors' => $errors,
            'loanTerms' => self::LOAN_TERMS,
        ]);
    }
}