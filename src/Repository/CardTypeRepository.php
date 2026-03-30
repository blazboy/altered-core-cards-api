<?php

namespace App\Repository;

use App\Entity\CardType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardType::class);
    }

    public function findOneByReference(string $reference): ?CardType
    {
        return $this->findOneBy(['reference' => $reference]);
    }
}
