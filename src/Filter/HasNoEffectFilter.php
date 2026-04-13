<?php

namespace App\Filter;

use App\Entity\Card;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters by presence or absence of effects.
 *
 * ?hasNoEffect=true   → cards with no effects at all
 * ?hasNoEffect=false  → cards with at least one effect
 */
final class HasNoEffectFilter extends AbstractFilter
{
    use CardSearchInClauseTrait;
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!$this->isPropertyEnabled($property, $resourceClass) || $value === '' || $value === null) {
            return;
        }

        $noEffect = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($noEffect === null) {
            return;
        }

        if ($resourceClass === Card::class) {
            $this->filterViaCardSearch($noEffect, $queryBuilder);
            return;
        }

        $root    = $queryBuilder->getRootAliases()[0];
        $through = $this->properties[$property] ?? null;

        if ($through) {
            $throughAlias = $queryNameGenerator->generateJoinAlias($through);
            $queryBuilder->leftJoin("$root.$through", $throughAlias);
            $joinRoot = $throughAlias;
        } else {
            $joinRoot = $root;
        }

        $a1 = $queryNameGenerator->generateJoinAlias('effect1');
        $a2 = $queryNameGenerator->generateJoinAlias('effect2');
        $a3 = $queryNameGenerator->generateJoinAlias('effect3');

        $queryBuilder
            ->leftJoin("$joinRoot.effect1", $a1)
            ->leftJoin("$joinRoot.effect2", $a2)
            ->leftJoin("$joinRoot.effect3", $a3);

        if ($noEffect) {
            $queryBuilder->andWhere("$a1.id IS NULL AND $a2.id IS NULL AND $a3.id IS NULL");
        } else {
            $queryBuilder->andWhere("$a1.id IS NOT NULL OR $a2.id IS NOT NULL OR $a3.id IS NOT NULL");
        }
    }

    private function filterViaCardSearch(bool $noEffect, QueryBuilder $qb): void
    {
        $sql  = $noEffect
            ? 'SELECT card_id FROM card_search WHERE has_effect = FALSE'
            : 'SELECT card_id FROM card_search WHERE has_effect = TRUE';

        $conn = $this->managerRegistry->getManager()->getConnection();
        $ids  = $conn->fetchFirstColumn($sql) ?: [0];

        $root = $qb->getRootAliases()[0];
        $this->applyIdInClause($qb, $root, $ids);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'hasNoEffect' => [
                'property'    => 'hasNoEffect',
                'type'        => 'bool',
                'required'    => false,
                'description' => 'true = cards with no effects; false = cards with at least one effect',
            ],
        ];
    }
}
