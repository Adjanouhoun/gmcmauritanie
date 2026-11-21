<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Homepage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 120)]
    public string $title = 'Qui sommes-nous ?';
    #[ORM\Column(type: 'text')]
    public string $presentation = 'General Mauritanien de Construction (GMC) est une entreprise de droit mauritanien fondée depuis 2009 et spécialisée dans la construction métallique, la formation professionnelle, la maintenance industrielle et la mécanique automobile.';
    #[ORM\Version, ORM\Column(type: 'integer')]
    private int $version = 1;

    public function getId(): ?int { return $this->id; }
    public function getVersion(): int { return $this->version; }
}
