<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ServiceOffering
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 80, unique: true)]
    private string $slug;
    #[ORM\Column(length: 120)]
    public string $title = '';
    #[ORM\Column(length: 200)]
    public string $listTitle = '';
    #[ORM\Column(length: 500)]
    public string $summary = '';
    #[ORM\Column(type: 'text')]
    public string $description = '';
    #[ORM\Column(length: 120)]
    public string $tagline = '';
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(string $slug) { $this->slug = $slug; }
    public function getId(): ?int { return $this->id; }
    public function getSlug(): string { return $this->slug; }
    public function getVersion(): int { return $this->version; }
}
