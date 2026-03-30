<?php

namespace App\Repository;

use App\Entity\AbilityTrigger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AbilityTrigger>
 */
class AbilityTriggerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbilityTrigger::class);
    }

    public function findByAlteredId(int $alteredId): ?AbilityTrigger
    {
        return $this->findOneBy(['alteredId' => $alteredId]);
    }

    /** @return array{0: AbilityTrigger[], 1: int} */
    public function findFiltered(string $q, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($q !== '') {
            $qb->andWhere('a.textFr LIKE :q OR a.textEn LIKE :q OR a.textDe LIKE :q OR a.textEs LIKE :q OR a.textIt LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

        $results = $qb->orderBy('a.alteredId', 'ASC')
                      ->setFirstResult(($page - 1) * $perPage)
                      ->setMaxResults($perPage)
                      ->getQuery()
                      ->getResult();

        return [$results, $total];
    }

    /** @return array<int, AbilityTrigger> keyed by alteredId */
    public function findAllIndexedByAlteredId(): array
    {
        $result = [];
        foreach ($this->findAll() as $entity) {
            $result[$entity->getAlteredId()] = $entity;
        }
        return $result;
    }
}
