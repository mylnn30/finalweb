<?php

namespace App\Controller;

use App\Entity\SystemSetting;
use App\Repository\SystemSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[IsGranted('ROLE_ADMIN')]
class AdminSettingsController extends AbstractController
{
    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, SystemSettingRepository $settings, EntityManagerInterface $entityManager): Response
    {
        $values = [];
        foreach (['default_interest_rate' => '4.99', 'late_payment_penalty_percent' => '2.00', 'grace_period_days' => '5'] as $key => $default) {
            $values[$key] = $settings->find($key)?->getSettingValue() ?? $default;
        }
        $form = $this->createFormBuilder($values)
            ->add('default_interest_rate', NumberType::class, ['scale' => 2, 'constraints' => [new Assert\PositiveOrZero()]])
            ->add('late_payment_penalty_percent', NumberType::class, ['scale' => 2, 'constraints' => [new Assert\PositiveOrZero()]])
            ->add('grace_period_days', IntegerType::class, ['constraints' => [new Assert\PositiveOrZero()]])
            ->add('save', SubmitType::class, ['label' => 'Save settings'])
            ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($form->getData() as $key => $value) {
                $setting = $settings->find($key) ?? (new SystemSetting())->setSettingKey($key);
                $setting->setSettingValue((string) $value);
                $entityManager->persist($setting);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Settings saved.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', ['form' => $form]);
    }
}