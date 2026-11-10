<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\RememberTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(Request $request, UserRepository $users): Response
    {
        $role = $request->query->get('role');

        return $this->render('admin/users/index.html.twig', [
            'users' => $users->searchUsers((string) $request->query->get('q', ''), is_string($role) ? $role : null),
            'query' => (string) $request->query->get('q', ''),
            'role' => is_string($role) ? $role : null,
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserRepository $users, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        return $this->buildNewUserForm($request, new User(), $users, $entityManager, $hasher);
    }

    #[Route('/{id}', name: 'admin_user_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, UserRepository $users): Response
    {
        $user = $users->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/users/show.html.twig', ['managedUser' => $user]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, UserRepository $users): Response
    {
        if (!$users->find($id)) {
            throw $this->createNotFoundException();
        }

        $this->addFlash('info', 'Personal information can only be updated by the account holder.');

        return $this->redirectToRoute('admin_user_show', ['id' => $id]);
    }

    #[Route('/{id}/access', name: 'admin_user_access', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function access(int $id, Request $request, UserRepository $users, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('user_access_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $user = $users->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }
        $action = (string) $request->request->get('action');
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId() && in_array($action, ['admin', 'staff', 'borrower', 'deactivate'], true)) {
            $this->addFlash('error', 'You cannot change your own role or deactivate your own account.');
        } elseif (in_array($action, ['admin', 'staff', 'borrower'], true)) {
            $user->setRole($action);
            $entityManager->flush();
        } elseif ($action === 'activate' || $action === 'deactivate') {
            $user->setIsActive($action === 'activate');
            $entityManager->flush();
        } else {
            throw $this->createNotFoundException('Unknown account action.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/reset-password', name: 'admin_user_reset_password', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function resetPassword(int $id, Request $request, UserRepository $users, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $user = $users->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Use Change Password to securely update your own password.');

            return $this->redirectToRoute('app_account_password');
        }

        $form = $this->createFormBuilder()
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New password'],
                'second_options' => ['label' => 'Confirm password'],
                'invalid_message' => 'Passwords do not match.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 8), new Assert\Regex('/[A-Z]/'), new Assert\Regex('/[a-z]/'), new Assert\Regex('/[0-9]/'), new Assert\Regex('/[\W_]/')],
            ])
            ->add('reset', SubmitType::class, ['label' => 'Reset password'])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('password')->getData()));
            $entityManager->flush();
            $this->addFlash('success', 'Password reset successfully.');

            return $this->redirectToRoute('admin_user_show', ['id' => $id]);
        }

        return $this->render('admin/users/reset_password.html.twig', ['managedUser' => $user, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, UserRepository $users, RememberTokenRepository $tokens, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('user_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $user = $users->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $id) {
            throw $this->createAccessDeniedException('You cannot delete your own account.');
        }
        if (!$user->getLoanApplications()->isEmpty() || !$user->getNotifications()->isEmpty()) {
            $this->addFlash('error', 'Users with loan or notification history cannot be deleted. Deactivate the account instead.');

            return $this->redirectToRoute('admin_user_show', ['id' => $id]);
        }

        $tokens->deleteForUserId($id);
        $entityManager->remove($user);
        $entityManager->flush();
        $this->addFlash('success', 'User deleted.');

        return $this->redirectToRoute('admin_users');
    }

    private function buildNewUserForm(Request $request, User $user, UserRepository $users, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $data = [
            'username' => '',
            'first_name' => '',
            'middle_name' => '',
            'last_name' => '',
            'email' => '',
            'phone_number' => '',
            'address' => '',
            'role' => 'borrower',
        ];
        $builder = $this->createFormBuilder($data)
            ->add('username', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(min: 4, max: 50), new Assert\Regex('/^[a-zA-Z0-9_]+$/')]])
            ->add('first_name', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]+$/")]])
            ->add('middle_name', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 50)]])
            ->add('last_name', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 50), new Assert\Regex("/^[a-zA-Z\\s'-]+$/")]])
            ->add('email', EmailType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 150), new Assert\Email()]])
            ->add('phone_number', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 20), new Assert\Regex('/^\+?[0-9\s\-()]+$/')]])
            ->add('address', TextareaType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)]])
            ->add('role', ChoiceType::class, ['choices' => ['Borrower' => 'borrower', 'Staff' => 'staff', 'Admin' => 'admin']])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm password'],
                'invalid_message' => 'Passwords do not match.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 8), new Assert\Regex('/[A-Z]/'), new Assert\Regex('/[a-z]/'), new Assert\Regex('/[0-9]/'), new Assert\Regex('/[\W_]/')],
            ]);
        $form = $builder->add('save', SubmitType::class, ['label' => 'Create account'])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $existingEmail = $users->findOneBy(['email' => $data['email']]);
            $existingUsername = $users->findOneBy(['username' => $data['username']]);
            if ($existingEmail || $existingUsername) {
                $form->addError(new FormError('Username or email is already in use.'));
            } else {
                $user->setUsername(trim($data['username']))
                    ->setFirstName(trim($data['first_name']))
                    ->setMiddleName(trim($data['middle_name'] ?? '') ?: null)
                    ->setLastName(trim($data['last_name']))
                    ->setEmail(trim($data['email']))
                    ->setPhoneNumber(trim($data['phone_number']))
                    ->setAddress(trim($data['address']))
                    ->setRole((string) $data['role'])
                    ->setPassword($hasher->hashPassword($user, $data['password']))
                    ->setIsActive(true);
                $entityManager->persist($user);
                $entityManager->flush();
                $this->addFlash('success', 'Account created.');

                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('admin/users/form.html.twig', ['form' => $form]);
    }
}