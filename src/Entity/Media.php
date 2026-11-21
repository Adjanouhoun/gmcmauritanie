<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
class Media
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 255)] public string $name = '';
    #[ORM\Column(length: 255)] public string $path = '';
    #[ORM\Column(length: 100)] public string $mime = '';
    #[ORM\Column(length: 300)] public string $alt = '';
    #[ORM\Column] public bool $uploaded = false;
    #[ORM\Column] public bool $archived = false;
    public function getId(): ?int { return $this->id; }
    public function isImage(): bool { return str_starts_with($this->mime,'image/'); }
}
