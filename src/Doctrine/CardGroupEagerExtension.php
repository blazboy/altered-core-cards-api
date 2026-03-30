<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\CardGroup;
use Doctrine\ORM\QueryBuilder;

/**
 * Eagerly loads all associations serialized in the card_group:read group
 * to avoid N+1 queries on the collection endpoint.
 *
 * Works in tandem with paginationFetchJoinCollection: true so that Doctrine's
 * paginator uses an ID-based subquery (avoiding row duplication from the
 * OneToMany / ManyToMany joins).
 */
final class CardGroupEagerExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if ($resourceClass !== CardGroup::class) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        // ManyToOne — FK on card_group table, no row-multiplication risk
        $queryBuilder
            ->leftJoin("$root.faction", 'eager_faction')->addSelect('eager_faction')
            ->leftJoin("$root.rarity", 'eager_rarity')->addSelect('eager_rarity')
            ->leftJoin("$root.cardType", 'eager_cardType')->addSelect('eager_cardType')
            ->leftJoin("$root.effect1", 'eager_effect1')->addSelect('eager_effect1')
            ->leftJoin("$root.effect2", 'eager_effect2')->addSelect('eager_effect2')
            ->leftJoin("$root.effect3", 'eager_effect3')->addSelect('eager_effect3')
            ->leftJoin("$root.cardHistoryStatus", 'eager_chs')->addSelect('eager_chs')
        ;

        // OneToMany / ManyToMany are NOT joined here: joining collections with
        // paginationFetchJoinCollection: false would multiply rows and break the
        // LIMIT-based pagination. They are batch-loaded by CardGroupCollectionProvider.
    }
}
