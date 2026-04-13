<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Card;
use Doctrine\ORM\QueryBuilder;

/**
 * Eagerly loads all associations needed for card:list serialization,
 * eliminating the N+1 query problem (~61 queries → 1 query).
 */
final class CardEagerLoadExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== Card::class) {
            return;
        }

        // Skip if already joined (another extension may have added them)
        $joins = array_column($queryBuilder->getDQLPart('join')[$queryBuilder->getRootAliases()[0]] ?? [], 'join');

        $root = $queryBuilder->getRootAliases()[0];

        if (!in_array("$root.cardGroup", $joins, true)) {
            // Only ManyToOne/OneToOne joins — never join collections (OneToMany/ManyToMany)
            // with pagination as it inflates row count and breaks LIMIT/OFFSET
            $queryBuilder
                ->addSelect('cg_el', 'f_el', 'r_el', 'chs_el', 's_el')
                ->leftJoin("$root.cardGroup", 'cg_el')
                ->leftJoin('cg_el.faction', 'f_el')
                ->leftJoin('cg_el.rarity', 'r_el')
                ->leftJoin('cg_el.cardHistoryStatus', 'chs_el')
                ->leftJoin("$root.set", 's_el');
        }
    }
}
