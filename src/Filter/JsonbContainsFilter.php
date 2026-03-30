<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Filter for JSONB @> (contains) operator on PostgreSQL.
 *
 * Usage:
 *   GET /main_effects?keywords[k]=CORIACE
 *   GET /main_effects?keywords[k]=CORIACE&keywords[v]=1
 *   GET /main_effects?keywords[k]=FUGACE
 */
final class JsonbContainsFilter extends AbstractFilter
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null,
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if (!$this->isPropertyEnabled($property, $resourceClass) || !is_array($value)) {
            return;
        }

        $keyword = $value['k'] ?? null;
        if (!$keyword) {
            return;
        }

        $alias     = $queryBuilder->getRootAliases()[0];
        $paramName = $queryNameGenerator->generateParameterName($property);
        $field     = sprintf('%s.%s', $alias, $property);

        $contains = ['k' => strtoupper($keyword)];
        if (isset($value['v'])) {
            $contains['v'] = (int) $value['v'];
        }

        $queryBuilder
            ->andWhere(sprintf('JSONB_CONTAINS(%s, :%s) = true', $field, $paramName))
            ->setParameter($paramName, json_encode([$contains], JSON_UNESCAPED_UNICODE));
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $_) {
            $description["{$property}[k]"] = [
                'property' => $property,
                'type'     => 'string',
                'required' => false,
                'description' => 'Filter by keyword name (e.g. CORIACE, FUGACE, REPERAGE)',
            ];
            $description["{$property}[v]"] = [
                'property' => $property,
                'type'     => 'integer',
                'required' => false,
                'description' => 'Filter by keyword value (optional, used with [k])',
            ];
        }

        return $description;
    }
}
