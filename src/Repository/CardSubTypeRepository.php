<?php

namespace App\Repository;

use App\Entity\CardSubType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardSubTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardSubType::class);
    }

    public function findOneByReference(string $reference): ?CardSubType
    {
        return $this->findOneBy(['reference' => $reference]);
    }
}
