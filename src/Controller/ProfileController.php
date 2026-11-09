<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/profile')]
class ProfileController extends AbstractBorrowerController
{
    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function show(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('profile/show.html.twig', ['user' => $this->getUser()]);
    }

    #[Route('/edit', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, UserRepository $users): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createFormBuilder($user)
            ->add('firstName', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]+$/")]])
            ->add('middleName', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]*$/")]])
            ->add('lastName', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]+$/")]])
            ->add('email', EmailType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150), new Assert\Email()]])
            ->add('address', TextareaType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('phoneNumber', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20), new Assert\Regex('/^\+?[0-9\s\-()]+$/')]])
            ->add('save', SubmitType::class, ['label' => 'Save changes'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $users->findOneBy(['email' => $user->getEmail()]);
            if ($existing !== null && $existing->getId() !== $user->getId()) {
                $form->get('email')->addError(new FormError('That email address is already in use.'));
            } else {
                $entityManager->flush();
                $this->addFlash('success', 'Profile updated.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/edit.html.twig', ['form' => $form]);
    }
}