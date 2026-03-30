<?php

namespace App\Entity;

use App\Model\TimestampInterface;
use App\Model\TimestampTrait;
use App\Repository\CollectionListRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CollectionListRepository::class)]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false)]
class CollectionList implements TimestampInterface
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'guid', unique: true)]
    protected ?string $id = null;

    #[ORM\Column(name: 'deletedAt', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private $deletedAt = null;

    #[ORM\Column(length: 50, nullable: false)]
    private string $name;

    #[ORM\Column(type: "boolean")]
    private bool $public = false;

    #[ORM\Column(type: "boolean")]
    private bool $wantedList = false;

    #[ORM\Column(type: "boolean")]
    private bool $tradeList = false;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'collectionList')]
    private UserInterface $user;

    #[ORM\OneToMany(targetEntity: CollectionCard::class, mappedBy: 'collectionList')]
    private Collection $cards;

    public function __construct()
    {
        $this->id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
        $this->cards = new ArrayCollection();
        $this->creationDate = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }


    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function isWantedList(): bool
    {
        return $this->wantedList;
    }

    public function setWantedList(bool $wantedList): self
    {
        $this->wantedList = $wantedList;

        return $this;
    }

    /**
     * @return Collection<int, CollectionCard>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(CollectionCard $collectionCard): self
    {
        $this->cards->add($collectionCard);

        return $this;
    }

    public function removeCard(CollectionCard $collectionCard): self
    {
        $this->cards->remove($collectionCard);

        return $this;
    }

    public function isTradeList(): bool
    {
        return $this->tradeList;
    }

    public function setTradeList(bool $tradeList): self
    {
        $this->tradeList = $tradeList;

        return $this;
    }
}