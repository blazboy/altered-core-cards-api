<?php

namespace App\Entity;

use App\Repository\CardRulingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CardRulingRepository::class)]
class CardRuling
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CardGroup::class, inversedBy: 'cardRulings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CardGroup $cardGroup = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['card_group:read'])]
    private string $question;

    #[ORM\Column(type: 'text')]
    #[Groups(['card_group:read'])]
    private string $answer;

    #[ORM\Column(length: 8, nullable: true)]
    #[Groups(['card_group:read'])]
    private ?string $locale = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['card_group:read'])]
    private ?string $eventFormat = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['card_group:read'])]
    private ?\DateTimeImmutable $rulingDate = null;

    public function getId(): ?int { return $this->id; }

    public function getCardGroup(): ?CardGroup { return $this->cardGroup; }
    public function setCardGroup(?CardGroup $cardGroup): self { $this->cardGroup = $cardGroup; return $this; }

    public function getLocale(): ?string { return $this->locale; }
    public function setLocale(?string $locale): self { $this->locale = $locale; return $this; }

    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $question): self { $this->question = $question; return $this; }

    public function getAnswer(): string { return $this->answer; }
    public function setAnswer(string $answer): self { $this->answer = $answer; return $this; }

    public function getEventFormat(): ?string { return $this->eventFormat; }
    public function setEventFormat(?string $eventFormat): self { $this->eventFormat = $eventFormat; return $this; }

    public function getRulingDate(): ?\DateTimeImmutable { return $this->rulingDate; }
    public function setRulingDate(?\DateTimeImmutable $rulingDate): self { $this->rulingDate = $rulingDate; return $this; }
}
