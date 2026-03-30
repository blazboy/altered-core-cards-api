<?php

namespace App\Model;

use Doctrine\ORM\Mapping as ORM;

trait TimestampTrait
{
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $creationDate;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updateDate = null;

    public function getCreationDate(): \DateTimeImmutable
    {
        return $this->creationDate;
    }

    public function setCreationDate(\DateTimeImmutable $creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    public function getUpdateDate(): ?\DateTimeImmutable
    {
        return $this->updateDate;
    }

    public function setUpdatedDate(\DateTimeImmutable $date): void
    {
        $this->updateDate = $date;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updateDate = new \DateTimeImmutable();
    }
}
