<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $amount = 250000.0;
        $term = 12;
        $monthlyPayment = null;
        $error = null;

        if ($request->isMethod('POST')) {
            $amount = (float) $request->request->get('loan_amount', 0);
            $term = (int) $request->request->get('loan_term', 0);

            if ($amount < 5000 || $amount > 50000000) {
                $error = 'Enter a loan amount between PHP 5,000 and PHP 50,000,000.';
            } elseif (!in_array($term, [12, 24, 36, 60], true)) {
                $error = 'Select a valid repayment period.';
            } else {
                $monthlyRate = 0.0499 / 12;
                $monthlyPayment = $amount * ($monthlyRate * (1 + $monthlyRate) ** $term)
                    / ((1 + $monthlyRate) ** $term - 1);
            }
        }

        return $this->render('site/home.html.twig', [
            'amount' => $amount,
            'term' => $term,
            'monthlyPayment' => $monthlyPayment,
            'error' => $error,
        ]);
    }
}