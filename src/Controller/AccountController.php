<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/account')]
class AccountController extends AbstractController
{
    #[Route('', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('account/index.html.twig', ['user' => $this->getUser()]);
    }

    #[Route('/password', name: 'app_account_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $hasher, \Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createFormBuilder()
            ->add('current_password', PasswordType::class, ['mapped' => false, 'constraints' => [new Assert\NotBlank()]])
            ->add('new_password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New password'],
                'second_options' => ['label' => 'Confirm new password'],
                'invalid_message' => 'Passwords do not match.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8),
                    new Assert\Regex('/[A-Z]/'),
                    new Assert\Regex('/[a-z]/'),
                    new Assert\Regex('/[0-9]/'),
                    new Assert\Regex('/[\W_]/'),
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Change password'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$hasher->isPasswordValid($user, (string) $form->get('current_password')->getData())) {
                $form->get('current_password')->addError(new \Symfony\Component\Form\FormError('The current password is incorrect.'));
            } else {
                $user->setPassword($hasher->hashPassword($user, (string) $form->get('new_password')->getData()));
                $entityManager->flush();
                $this->addFlash('success', 'Password changed successfully.');

                return $this->redirectToRoute('app_account');
            }
        }

        return $this->render('account/password.html.twig', ['form' => $form]);
    }
}
