<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CollectionCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "integer", nullable: false)]
    private int $quantity;

    #[ORM\ManyToOne(targetEntity: CollectionList::class, inversedBy: 'cards')]
    private CollectionList $collectionList;

    #[ORM\ManyToOne(targetEntity: Card::class)]
    private Card $card;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getCollectionList(): CollectionList
    {
        return $this->collectionList;
    }

    public function setCollectionList(CollectionList $collectionList): self
    {
        $this->collectionList = $collectionList;

        return $this;
    }

    public function getCard(): Card
    {
        return $this->card;
    }

    public function setCard(Card $card): self
    {
        $this->card = $card;

        return $this;
    }
}