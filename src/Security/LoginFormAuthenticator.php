<?php

namespace App\Security;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginAttemptRepository $attempts,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $username = trim((string) $request->request->get('_username', ''));
        $password = (string) $request->request->get('_password', '');
        if ($username === '') {
            throw new CustomUserMessageAuthenticationException('Username is required.');
        }
        if ($password === '') {
            throw new CustomUserMessageAuthenticationException('Password is required.');
        }
        $since = new \DateTimeImmutable('-' . self::LOCKOUT_MINUTES . ' minutes');

        if ($this->attempts->countRecentForUsername($username, $since) >= self::MAX_ATTEMPTS) {
            throw new CustomUserMessageAuthenticationException('Too many failed login attempts. Please try again in a few minutes.');
        }

        return new Passport(
            new UserBadge($username, fn (string $identifier): ?UserInterface => $this->users->findOneBy(['username' => $identifier])),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token', '')),
                new RememberMeBadge(),
            ],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $this->attempts->clearForUsername($user->getUsername());
            $this->entityManager->flush();
        }

        $route = match (true) {
            $user instanceof User && $user->isAdmin() => 'admin_dashboard',
            $user instanceof User && $user->isStaff() => 'staff_dashboard',
            default => 'app_dashboard',
        };

        return new RedirectResponse($this->urlGenerator->generate($route));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $username = trim((string) $request->request->get('_username', ''));
        $since = new \DateTimeImmutable('-' . self::LOCKOUT_MINUTES . ' minutes');
        if ($username !== '' && $request->request->get('_password') !== null && $request->request->get('_password') !== ''
            && $this->attempts->countRecentForUsername($username, $since) < self::MAX_ATTEMPTS) {
            $this->entityManager->persist((new LoginAttempt())->setUsername($username));
            $this->entityManager->flush();
        }

        return parent::onAuthenticationFailure($request, $exception);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}