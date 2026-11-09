<?php

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Table(name: 'login_attempts')]
#[ORM\Index(name: 'idx_username_time', columns: ['username', 'attempted_at'])]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'username', type: 'string', length: 50)]
    private string $username;

    #[ORM\Column(name: 'attempted_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $attemptedAt;

    public function __construct()
    {
        $this->attemptedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getAttemptedAt(): \DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(\DateTimeImmutable $attemptedAt): self
    {
        $this->attemptedAt = $attemptedAt;

        return $this;
    }
}