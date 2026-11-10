<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user', options: ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_general_ci', 'engine' => 'InnoDB'])]
#[ORM\UniqueConstraint(name: 'username', columns: ['username'])]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'username', type: 'string', length: 50)]
    private string $username;

    #[ORM\Column(name: 'first_name', type: 'string', length: 50)]
    private string $firstName;

    #[ORM\Column(name: 'middle_name', type: 'string', length: 50, nullable: true)]
    private ?string $middleName = null;

    #[ORM\Column(name: 'last_name', type: 'string', length: 50)]
    private string $lastName;

    #[ORM\Column(name: 'email', type: 'string', length: 150)]
    private string $email;

    #[ORM\Column(name: 'address', type: 'string', length: 255)]
    private string $address;

    #[ORM\Column(name: 'password', type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(name: 'is_admin', type: 'boolean', options: ['default' => 0])]
    private bool $isAdmin = false;

    #[ORM\Column(name: 'is_staff', type: 'boolean', options: ['default' => 0])]
    private bool $isStaff = false;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => 1])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'], columnDefinition: 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'phone_number', type: 'string', length: 20)]
    private string $phoneNumber;

    /** @var Collection<int, LoanApplication> */
    #[ORM\OneToMany(targetEntity: LoanApplication::class, mappedBy: 'user')]
    private Collection $loanApplications;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    /** @var Collection<int, LoanApplication> */
    #[ORM\OneToMany(targetEntity: LoanApplication::class, mappedBy: 'assignedStaff')]
    private Collection $assignedApplications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->loanApplications = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->assignedApplications = new ArrayCollection();
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

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        if ($this->isAdmin) {
            return ['ROLE_USER', 'ROLE_ADMIN'];
        }

        return $this->isStaff ? ['ROLE_USER', 'ROLE_STAFF'] : ['ROLE_USER', 'ROLE_BORROWER'];
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin): self
    {
        $this->isAdmin = $isAdmin;
        if ($isAdmin) {
            $this->isStaff = false;
        }

        return $this;
    }

    public function isStaff(): bool
    {
        return $this->isStaff;
    }

    public function setIsStaff(bool $isStaff): self
    {
        $this->isStaff = $isStaff;
        if ($isStaff) {
            $this->isAdmin = false;
        }

        return $this;
    }

    public function setRole(string $role): self
    {
        $this->isAdmin = $role === 'admin';
        $this->isStaff = $role === 'staff';

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function setMiddleName(?string $middleName): self
    {
        $this->middleName = $middleName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /** @return Collection<int, LoanApplication> */
    public function getLoanApplications(): Collection
    {
        return $this->loanApplications;
    }

    public function addLoanApplication(LoanApplication $loanApplication): self
    {
        if (!$this->loanApplications->contains($loanApplication)) {
            $this->loanApplications->add($loanApplication);
            $loanApplication->setUser($this);
        }

        return $this;
    }

    public function removeLoanApplication(LoanApplication $loanApplication): self
    {
        $this->loanApplications->removeElement($loanApplication);

        return $this;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        $this->notifications->removeElement($notification);

        return $this;
    }

    /** @return Collection<int, LoanApplication> */
    public function getAssignedApplications(): Collection
    {
        return $this->assignedApplications;
    }

    public function addAssignedApplication(LoanApplication $application): self
    {
        if (!$this->assignedApplications->contains($application)) {
            $this->assignedApplications->add($application);
        }

        return $this;
    }

    public function removeAssignedApplication(LoanApplication $application): self
    {
        $this->assignedApplications->removeElement($application);

        return $this;
    }
}