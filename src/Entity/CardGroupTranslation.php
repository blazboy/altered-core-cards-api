<?php

namespace App\Entity;

use App\Model\TimestampInterface;
use App\Model\TimestampTrait;
use App\Repository\CardGroupTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\UniqueConstraint(fields: ['cardGroup', 'locale'])]
#[ORM\Entity(repositoryClass: CardGroupTranslationRepository::class)]
class CardGroupTranslation implements TimestampInterface
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CardGroup::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CardGroup $cardGroup;

    #[ORM\Column(length: 8)]
    private string $locale;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $mainEffect = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $echoEffect = null;

    public function __construct()
    {
        $this->creationDate = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCardGroup(): CardGroup { return $this->cardGroup; }
    public function setCardGroup(CardGroup $cardGroup): self { $this->cardGroup = $cardGroup; return $this; }

    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $locale): self { $this->locale = $locale; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getMainEffect(): ?string { return $this->mainEffect; }
    public function setMainEffect(?string $mainEffect): self { $this->mainEffect = $mainEffect; return $this; }

    public function getEchoEffect(): ?string { return $this->echoEffect; }
    public function setEchoEffect(?string $echoEffect): self { $this->echoEffect = $echoEffect; return $this; }
}
