<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $data = [];
        $form = $this->createFormBuilder($data)
            ->add('username', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(min: 4, max: 50), new Assert\Regex('/^[a-zA-Z0-9_]+$/')]])
            ->add('first_name', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]+$/")]])
            ->add('middle_name', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]*$/")]])
            ->add('last_name', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]+$/")]])
            ->add('email', EmailType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150), new Assert\Email()]])
            ->add('phone_number', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20), new Assert\Regex('/^\+?[0-9\s\-()]+$/')]])
            ->add('address', TextareaType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm password'],
                'invalid_message' => 'Passwords do not match.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 8), new Assert\Regex('/[A-Z]/'), new Assert\Regex('/[a-z]/'), new Assert\Regex('/[0-9]/'), new Assert\Regex('/[\W_]/')],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Create account'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($users->findOneBy(['username' => $data['username']]) || $users->findOneBy(['email' => $data['email']])) {
                $form->addError(new FormError('Username or email is already registered.'));
            } else {
                $user = (new User())
                    ->setUsername(trim($data['username']))
                    ->setFirstName(trim($data['first_name']))
                    ->setMiddleName(trim($data['middle_name'] ?? '') ?: null)
                    ->setLastName(trim($data['last_name']))
                    ->setEmail(trim($data['email']))
                    ->setPhoneNumber(trim($data['phone_number']))
                    ->setAddress(trim($data['address']))
                    ->setIsActive(true)
                    ->setIsAdmin(false);
                $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Account created. You can now sign in.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/new.html.twig', ['form' => $form]);
    }
}