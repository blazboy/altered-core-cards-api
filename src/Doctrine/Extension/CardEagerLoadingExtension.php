<?php

namespace App\Doctrine\Extension;

use App\Entity\Card;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Eagerly loads associations that are accessed by virtual getters on Card but
 * are invisible to API Platform's EagerLoadingExtension (no serialization group).
 *
 * Runs at priority -19, just after EagerLoadingExtension (-18), so that
 * EagerLoadingExtension's joins (e.g. cardGroup) are already in the QueryBuilder
 * and we can graft our extra joins onto their aliases.
 */
#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.collection', ['priority' => -19])]
final class CardEagerLoadingExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        ?string $resourceClass = null,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (Card::class !== $resourceClass) {
            return;
        }

        // When fetchJoinCollection is explicitly disabled, the paginator uses a simple
        // LIMIT on SQL rows. Joining OneToMany associations (translations) would cause
        // row multiplication and return fewer cards per page than expected.
        if ($operation?->getPaginationFetchJoinCollection() === false) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Card.translations is not in any serialization group but is iterated by
        // getLocalizedImagePaths() which is exposed in card:read.
        $cardTransAlias = $queryNameGenerator->generateJoinAlias('card_translations');
        QueryBuilderHelper::addJoinOnce(
            $queryBuilder, $queryNameGenerator,
            $rootAlias, 'translations',
            Join::LEFT_JOIN, null, null, $rootAlias, $cardTransAlias,
        );
        $queryBuilder->addSelect($cardTransAlias);

        // EagerLoadingExtension already joined cardGroup; find its alias and
        // also join cardGroup.translations (used by getLocalizedNames() etc. in card:read).
        $cgJoin = QueryBuilderHelper::getExistingJoin($queryBuilder, $rootAlias, 'cardGroup', $rootAlias);
        if ($cgJoin === null) {
            return;
        }

        $cgTransAlias = $queryNameGenerator->generateJoinAlias('cardGroup_translations');
        QueryBuilderHelper::addJoinOnce(
            $queryBuilder, $queryNameGenerator,
            $cgJoin->getAlias(), 'translations',
            Join::LEFT_JOIN, null, null, $rootAlias, $cgTransAlias,
        );
        $queryBuilder->addSelect($cgTransAlias);
    }
}
