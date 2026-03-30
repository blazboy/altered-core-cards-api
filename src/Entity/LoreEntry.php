<?php

namespace App\Entity;

use App\Repository\LoreEntryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LoreEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_lore_entry_group', fields: ['cardGroup', 'alteredId', 'locale'])]
class LoreEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CardGroup::class, inversedBy: 'loreEntries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CardGroup $cardGroup = null;

    #[ORM\Column(length: 50)]
    private string $alteredId;

    #[ORM\Column(length: 8)]
    #[Groups(['card_group:read'])]
    private string $locale;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['card_group:read'])]
    private ?string $type = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['card_group:read'])]
    private array $elements = [];

    public function getId(): ?int { return $this->id; }

    public function getCardGroup(): ?CardGroup { return $this->cardGroup; }
    public function setCardGroup(?CardGroup $cardGroup): self { $this->cardGroup = $cardGroup; return $this; }

    public function getAlteredId(): string { return $this->alteredId; }
    public function setAlteredId(string $alteredId): self { $this->alteredId = $alteredId; return $this; }

    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): self { $this->locale = $locale; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): self { $this->type = $type; return $this; }

    public function getElements(): array { return $this->elements; }
    public function setElements(array $elements): self { $this->elements = $elements; return $this; }
}
