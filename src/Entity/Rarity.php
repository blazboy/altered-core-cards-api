<?php

namespace App\Entity;

use App\Repository\RarityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RarityRepository::class)]
class Rarity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['card:read', 'card_group:read', 'card:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Groups(['card:read', 'card_group:read', 'card:list'])]
    private string $reference;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nameFr = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nameEn = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nameIt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nameEs = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nameDe = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['card:read', 'card_group:read'])]
    private ?int $position = null;

    #[Groups(['card:read', 'card_group:read'])]
    public function getName(): array
    {
        return array_filter([
            'fr' => $this->nameFr,
            'en' => $this->nameEn,
            'it' => $this->nameIt,
            'es' => $this->nameEs,
            'de' => $this->nameDe,
        ]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getNameFr(): ?string
    {
        return $this->nameFr;
    }

    public function setNameFr(?string $nameFr): self
    {
        $this->nameFr = $nameFr;

        return $this;
    }

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): self
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getNameIt(): ?string
    {
        return $this->nameIt;
    }

    public function setNameIt(?string $nameIt): self
    {
        $this->nameIt = $nameIt;

        return $this;
    }

    public function getNameEs(): ?string
    {
        return $this->nameEs;
    }

    public function setNameEs(?string $nameEs): self
    {
        $this->nameEs = $nameEs;

        return $this;
    }

    public function getNameDe(): ?string
    {
        return $this->nameDe;
    }

    public function setNameDe(?string $nameDe): self
    {
        $this->nameDe = $nameDe;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getNameForLocale(string $locale): ?string
    {
        return match ($locale) {
            'fr' => $this->nameFr,
            'en' => $this->nameEn,
            'it' => $this->nameIt,
            'es' => $this->nameEs,
            'de' => $this->nameDe,
            default => $this->nameEn,
        };
    }
}
