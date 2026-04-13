<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Search\SearchBackendInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Name search with pluggable backend (Meilisearch, …) + PostgreSQL LIKE fallback.
 *
 * name=foo          → full-text across all locales
 * name[fr]=foo      → full-text on French name/effects only
 * name[en]=foo      → full-text on English name/effects only
 *
 * If the backend returns null (unavailable / NullSearchBackend), falls back
 * to a PostgreSQL LIKE query on cardGroup.translations.name.
 */
final class CardNameFilter extends AbstractFilter
{
    private SearchBackendInterface $searchBackend;

    #[Required]
    public function setSearchBackend(SearchBackendInterface $searchBackend): void
    {
        $this->searchBackend = $searchBackend;
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
        if ($property !== 'name' || $value === null || $value === '' || $value === []) {
            return;
        }

        $root = $queryBuilder->getRootAliases()[0];

        // ── Search backend fast path ─────────────────────────────────────────
        $ids = $this->resolveWithBackend($value);

        if ($ids !== null) {
            if (empty($ids)) {
                $queryBuilder->andWhere('1 = 0');
                return;
            }

            $p = $queryNameGenerator->generateParameterName('search_ids');
            $queryBuilder
                ->andWhere("$root.id IN (:$p)")
                ->setParameter($p, $ids);

            return;
        }

        // ── PostgreSQL LIKE fallback ─────────────────────────────────────────
        $cgAlias = $this->getOrJoinCardGroup($queryBuilder, $root);

        if (is_string($value)) {
            $search = trim($value);
            if ($search === '') return;
            $tAlias = $queryNameGenerator->generateJoinAlias('cgt');
            $pName  = $queryNameGenerator->generateParameterName('name_search');
            $queryBuilder
                ->leftJoin("$cgAlias.translations", $tAlias)
                ->andWhere($queryBuilder->expr()->like("LOWER($tAlias.name)", ":$pName"))
                ->setParameter($pName, '%' . mb_strtolower($search) . '%');
            return;
        }

        $orParts = [];
        foreach ($value as $locale => $search) {
            $search = trim((string) $search);
            if ($search === '') continue;

            $tAlias = $queryNameGenerator->generateJoinAlias('cgt');
            $pLoc   = $queryNameGenerator->generateParameterName('name_locale');
            $pName  = $queryNameGenerator->generateParameterName('name_search');

            $queryBuilder
                ->leftJoin("$cgAlias.translations", $tAlias, 'WITH', "$tAlias.locale = :$pLoc")
                ->setParameter($pLoc, $locale);

            $orParts[] = $queryBuilder->expr()->like("LOWER($tAlias.name)", ":$pName");
            $queryBuilder->setParameter($pName, '%' . mb_strtolower($search) . '%');
        }

        if (!empty($orParts)) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$orParts));
        }
    }

    /**
     * Ask the backend for matching IDs.
     * Returns null if the backend is unavailable (triggers LIKE fallback).
     *
     * @return int[]|null
     */
    private function resolveWithBackend(string|array $value): ?array
    {
        if (is_string($value)) {
            return $this->searchBackend->searchCardIds(trim($value));
        }

        // locale-specific search — OR between locales
        $allIds = null;
        foreach ($value as $locale => $search) {
            $search = trim((string) $search);
            if ($search === '') continue;

            $ids = $this->searchBackend->searchCardIds($search, ["name_{$locale} EXISTS"]);
            if ($ids === null) {
                return null; // backend unavailable, trigger fallback
            }

            $allIds = $allIds === null
                ? $ids
                : array_values(array_unique(array_merge($allIds, $ids)));
        }

        return $allIds;
    }

    private function getOrJoinCardGroup(QueryBuilder $qb, string $root): string
    {
        foreach ($qb->getDQLPart('join')[$root] ?? [] as $join) {
            if ($join->getJoin() === "$root.cardGroup") {
                return $join->getAlias();
            }
        }
        $alias = 'alias_cg_name';
        $qb->join("$root.cardGroup", $alias);
        return $alias;
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'name' => [
                'property'    => 'name',
                'type'        => 'string',
                'required'    => false,
                'description' => 'Full-text search across all locales (Meilisearch)',
            ],
            'name[fr]' => [
                'property' => 'name',
                'type'     => 'string',
                'required' => false,
            ],
            'name[en]' => [
                'property' => 'name',
                'type'     => 'string',
                'required' => false,
            ],
        ];
    }
}
