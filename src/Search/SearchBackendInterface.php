<?php

namespace App\Search;

/**
 * Contract for full-text search backends used by API filters.
 *
 * Returns a list of matching Card IDs, or null if the backend
 * is unavailable / not configured (triggers PostgreSQL fallback).
 */
interface SearchBackendInterface
{
    /**
     * @param string[] $attributesToSearchOn  Restrict search to specific fields (empty = all)
     * @return int[]|null  null = backend unavailable, use fallback
     */
    public function searchCardIds(string $query, array $attributesToSearchOn = []): ?array;
}
