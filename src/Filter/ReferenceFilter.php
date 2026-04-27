<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Allows filtering on relation reference fields using a flat query parameter.
 * e.g. ?rarity=COMMON maps to WHERE rarity.reference = 'COMMON'
 *
 * Usage:
 * #[ApiFilter(ReferenceFilter::class, properties: ['rarity', 'cardType'])]
 */
final class ReferenceFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!$this->isPropertyEnabled($property, $resourceClass) || $value === null || $value === '') {
            return;
        }

        $alias     = $queryBuilder->getRootAliases()[0];
        $paramName = $queryNameGenerator->generateParameterName($property);

        $metadata      = $this->getManagerRegistry()->getManagerForClass($resourceClass)->getClassMetadata($resourceClass);
        $targetClass   = $metadata->getAssociationTargetClass($property);
        $subAlias      = $queryNameGenerator->generateJoinAlias($property);

        $subDql = sprintf(
            'SELECT %s.id FROM %s %s WHERE %s.reference IN (:%s)',
            $subAlias, $targetClass, $subAlias, $subAlias, $paramName,
        );

        $queryBuilder
            ->andWhere(sprintf('IDENTITY(%s.%s) IN (%s)', $alias, $property, $subDql))
            ->setParameter($paramName, (array) $value);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type'     => 'string',
                'required' => false,
                'description' => sprintf('Filter by %s reference (e.g. %s=COMMON)', $property, $property),
            ];
        }

        return $description;
    }
}
