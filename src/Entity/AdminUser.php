<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 180, unique: true)]
    private string $email;
    #[ORM\Column]
    private string $password = '';

    public function __construct(string $email) { $this->email = mb_strtolower(trim($email)); }
    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return ['ROLE_ADMIN']; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hash): void { $this->password = $hash; }
    public function eraseCredentials(): void {}
}
