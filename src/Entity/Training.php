<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_training_slug', columns: ['slug'])]
class Training
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 80)]
    private string $slug;
    #[ORM\Column(length: 200)]
    public string $title = '';
    #[ORM\Column(length: 120)]
    public string $shortTitle = '';
    #[ORM\Column(type: 'text')]
    public string $description = '';
    #[ORM\Column(type: 'text')]
    public string $objectives = '';
    #[ORM\Column(length: 500)]
    public string $audience = '';
    #[ORM\Column(length: 120)]
    public string $duration = '';
    #[ORM\Column(length: 500)]
    public string $note = '';
    #[ORM\Column]
    private bool $featured;
    #[ORM\Column(length: 500)]
    public string $summary = '';
    #[ORM\Column(length: 120)]
    public string $homeDuration = '';
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(string $slug, bool $featured = false) { $this->slug = $slug; $this->featured = $featured; }
    public function getId(): ?int { return $this->id; }
    public function getSlug(): string { return $this->slug; }
    public function isFeatured(): bool { return $this->featured; }
    public function getVersion(): int { return $this->version; }
}
