<?php

namespace App\Repository;

use App\Entity\CardHistoryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardHistoryStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardHistoryStatus::class);
    }

    public function findOneByReference(string $reference): ?CardHistoryStatus
    {
        return $this->findOneBy(['reference' => $reference]);
    }
}
