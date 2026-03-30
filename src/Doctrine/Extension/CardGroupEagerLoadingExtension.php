<?php

namespace App\Doctrine\Extension;

use App\Entity\CardGroup;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Eagerly loads associations that are accessed by virtual getters on CardGroup
 * but are invisible to API Platform's EagerLoadingExtension (no serialization group).
 *
 * Missing associations:
 *   - CardGroup.translations  → getLocalizedNames(), getLocalizedMainEffects(), getLocalizedEchoEffects()
 *   - CardGroup.cardRulings   → getSerializedCardRulings()
 *   - CardGroup.loreEntries   → getSerializedLoreEntries()
 *   - Card.translations (nested inside CardGroup.cards) → Card::getLocalizedImagePaths()
 *
 * Runs at priority -19, just after EagerLoadingExtension (-18), so that the
 * `cards` join added by EagerLoadingExtension is already present and we can
 * graft Card.translations onto its alias.
 */
#[AutoconfigureTag('api_platform.doctrine.orm.query_extension.collection', ['priority' => -19])]
final class CardGroupEagerLoadingExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        ?string $resourceClass = null,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (CardGroup::class !== $resourceClass) {
            return;
        }

        // When fetchJoinCollection is explicitly disabled, the paginator uses a simple
        // LIMIT on SQL rows. Joining OneToMany associations (translations, cardRulings,
        // loreEntries, cards.translations) would cause row multiplication and return
        // fewer card groups per page than expected.
        if ($operation?->getPaginationFetchJoinCollection() === false) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];

        // CardGroup.translations → getLocalizedNames(), getLocalizedMainEffects(), getLocalizedEchoEffects()
        $cgTransAlias = $queryNameGenerator->generateJoinAlias('cg_translations');
        QueryBuilderHelper::addJoinOnce(
            $queryBuilder, $queryNameGenerator,
            $rootAlias, 'translations',
            Join::LEFT_JOIN, null, null, $rootAlias, $cgTransAlias,
        );
        $queryBuilder->addSelect($cgTransAlias);

        // CardGroup.cardRulings → getSerializedCardRulings()
        $rulingAlias = $queryNameGenerator->generateJoinAlias('cg_rulings');
        QueryBuilderHelper::addJoinOnce(
            $queryBuilder, $queryNameGenerator,
            $rootAlias, 'cardRulings',
            Join::LEFT_JOIN, null, null, $rootAlias, $rulingAlias,
        );
        $queryBuilder->addSelect($rulingAlias);

        // CardGroup.loreEntries → getSerializedLoreEntries()
        $loreAlias = $queryNameGenerator->generateJoinAlias('cg_lore');
        QueryBuilderHelper::addJoinOnce(
            $queryBuilder, $queryNameGenerator,
            $rootAlias, 'loreEntries',
            Join::LEFT_JOIN, null, null, $rootAlias, $loreAlias,
        );
        $queryBuilder->addSelect($loreAlias);

        // EagerLoadingExtension already joined CardGroup.cards; find its alias and
        // also join Card.translations (used by getLocalizedImagePaths() in card_group:read).
        $cardsJoin = QueryBuilderHelper::getExistingJoin($queryBuilder, $rootAlias, 'cards', $rootAlias);
        if ($cardsJoin === null) {
            return;
        }

        $cardTransAlias = $queryNameGenerator->generateJoinAlias('card_translations');
        QueryBuilderHelper::addJoinOnce(
            $queryBuilder, $queryNameGenerator,
            $cardsJoin->getAlias(), 'translations',
            Join::LEFT_JOIN, null, null, $rootAlias, $cardTransAlias,
        );
        $queryBuilder->addSelect($cardTransAlias);
    }
}
