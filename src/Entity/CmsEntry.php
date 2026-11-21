<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_cms_type_slug', columns: ['type','slug'])]
class CmsEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 30)] public string $type;
    #[ORM\Column(length: 160)] public string $slug;
    #[ORM\Column(length: 200)] public string $title = '';
    #[ORM\Column(length: 20)] public string $status = 'draft';
    #[ORM\Column] public int $position = 0;
    #[ORM\Column(type: 'json')] public array $data = [];
    #[ORM\Version, ORM\Column(type: 'integer')] private int $version = 1;
    public function __construct(string $type, string $slug) { $this->type=$type; $this->slug=$slug; }
    public function getId(): ?int { return $this->id; }
    public function getVersion(): int { return $this->version; }
}
