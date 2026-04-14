<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filters cards by artist reference.
 * Usage: GET /api/cards?artist=Gamon%20Studio
 */
final class ArtistFilter extends AbstractFilter
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
        if ($property !== 'artist' || $value === '' || $value === null) {
            return;
        }

        $root    = $queryBuilder->getRootAliases()[0];
        $aAlias  = $queryNameGenerator->generateJoinAlias('artist');
        $pArtist = $queryNameGenerator->generateParameterName('artist');

        $queryBuilder
            ->join("$root.artists", $aAlias)
            ->andWhere("$aAlias.reference = :$pArtist")
            ->setParameter($pArtist, $value);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'artist' => [
                'property' => 'artist',
                'type'     => 'string',
                'required' => false,
                'openapi'  => ['description' => 'Filter by artist reference (name)'],
            ],
        ];
    }
}
