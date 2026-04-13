<?php

namespace App\Filter;

use App\Entity\Card;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters by ability_trigger id across any of the three effect slots.
 *
 * On Card: DBAL lookup on card_search (t1, t2, t3 columns).
 * On CardGroup: JOIN-based fallback.
 */
final class EffectTriggerTypeFilter extends AbstractFilter
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
        if (!$this->isPropertyEnabled($property, $resourceClass) || empty($value)) {
            return;
        }

        $triggerId = (int) $value;
        if ($triggerId <= 0) {
            return;
        }

        if ($resourceClass === Card::class) {
            $conn = $this->managerRegistry->getManager()->getConnection();
            $ids  = $conn->fetchFirstColumn(
                'SELECT card_id FROM card_search WHERE t1 = :tid OR t2 = :tid OR t3 = :tid',
                ['tid' => $triggerId],
            ) ?: [0];

            $root = $queryBuilder->getRootAliases()[0];
            $this->applyIdInClause($queryBuilder, $root, $ids);

            return;
        }

        // Fallback: JOIN on effect slots
        $root    = $queryBuilder->getRootAliases()[0];
        $param   = $queryNameGenerator->generateParameterName($property);
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
            ->leftJoin("$joinRoot.effect3", $a3)
            ->andWhere(
                "IDENTITY($a1.abilityTrigger) = :$param
                 OR IDENTITY($a2.abilityTrigger) = :$param
                 OR IDENTITY($a3.abilityTrigger) = :$param"
            )
            ->setParameter($param, $triggerId);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'effectTriggerType' => [
                'property'    => 'effectTriggerType',
                'type'        => 'int',
                'required'    => false,
                'description' => 'Filter by ability_trigger id on any of the three effect slots',
            ],
        ];
    }
}
