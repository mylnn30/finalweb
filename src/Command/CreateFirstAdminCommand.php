<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:admin:create-first',
    description: 'Create the first administrator account when no administrator exists',
)]
class CreateFirstAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->users->findOneBy(['isAdmin' => true])) {
            $io->error('An administrator already exists. Use the Admin user-management page to create additional Admin accounts.');

            return Command::FAILURE;
        }

        if (!$input->isInteractive()) {
            $io->error('Run this command interactively so the password can be entered securely.');

            return Command::FAILURE;
        }

        $io->title('Create the first Admin account');
        $username = $this->askValue($io, 'Username', static fn (string $value): ?string => preg_match('/^[a-zA-Z0-9_]{4,50}$/', $value) === 1
            ? null
            : 'Use 4-50 letters, numbers, or underscores.');
        if ($this->users->findOneBy(['username' => $username])) {
            $io->error('That username is already in use.');

            return Command::FAILURE;
        }

        $firstName = $this->askValue($io, 'First name', static fn (string $value): ?string => preg_match("/^[a-zA-Z\\s'-]{1,50}$/", $value) === 1
            ? null
            : 'Enter a name of up to 50 letters.');
        $lastName = $this->askValue($io, 'Last name', static fn (string $value): ?string => preg_match("/^[a-zA-Z\\s'-]{1,50}$/", $value) === 1
            ? null
            : 'Enter a name of up to 50 letters.');
        $email = $this->askValue($io, 'Email', static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 150
            ? null
            : 'Enter a valid email address of up to 150 characters.');
        if ($this->users->findOneBy(['email' => $email])) {
            $io->error('That email address is already in use.');

            return Command::FAILURE;
        }

        $phoneNumber = $this->askValue($io, 'Phone number', static fn (string $value): ?string => preg_match('/^\+?[0-9\s\-()]{1,20}$/', $value) === 1
            ? null
            : 'Enter a valid phone number of up to 20 characters.');
        $address = $this->askValue($io, 'Address', static fn (string $value): ?string => $value !== '' && strlen($value) <= 255
            ? null
            : 'Address is required and must be at most 255 characters.');

        $password = $io->askHidden('Admin password');
        if (!is_string($password)
            || strlen($password) < 8
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1
            || preg_match('/[\W_]/', $password) !== 1
        ) {
            $io->error('Use at least 8 characters with uppercase and lowercase letters, a number, and a symbol.');

            return Command::FAILURE;
        }

        if ($password !== $io->askHidden('Confirm admin password')) {
            $io->error('The passwords do not match.');

            return Command::FAILURE;
        }

        $admin = (new User())
            ->setUsername($username)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setPhoneNumber($phoneNumber)
            ->setAddress($address)
            ->setRole('admin')
            ->setIsActive(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $password));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Admin account "%s" created. Sign in at /login, then open /admin.', $username));

        return Command::SUCCESS;
    }

    /**
     * @param callable(string): ?string $validate
     */
    private function askValue(SymfonyStyle $io, string $question, callable $validate): string
    {
        while (true) {
            $value = trim((string) $io->ask($question));
            $error = $validate($value);
            if ($error === null) {
                return $value;
            }

            $io->error($error);
        }
    }
}
