<?php

namespace App\Repository;

use App\Entity\LoreEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LoreEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoreEntry::class);
    }

    public function findOneByCardAlteredIdAndLocale(int $cardId, string $alteredId, string $locale): ?LoreEntry
    {
        return $this->createQueryBuilder('l')
            ->where('l.card = :cardId')
            ->andWhere('l.alteredId = :alteredId')
            ->andWhere('l.locale = :locale')
            ->setParameter('cardId', $cardId)
            ->setParameter('alteredId', $alteredId)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
