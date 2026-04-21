<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class BgaQueryFilter extends AbstractFilter
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
        // This filter doesn't modify the query - it only sets a context flag
        // The actual work is done in the provider via $context['filters']['bga']
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'bga' => [
                'property' => 'bga',
                'type' => 'bool',
                'required' => false,
                'description' => 'Request BGA-formatted abilities (triggerId, conditionId, effectId)',
            ],
        ];
    }
}