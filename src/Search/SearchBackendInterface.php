<?php

namespace App\Search;

use App\Entity\Card;

/**
 * Contract for full-text search backends used by API filters and indexing.
 *
 * Read: searchCardIds() returns matching Card IDs, or null to trigger PostgreSQL fallback.
 * Write: indexCard() / deleteCard() keep the index in sync with DB changes.
 */
interface SearchBackendInterface
{
    /**
     * @param string[] $attributesToSearchOn  Restrict search to specific fields (empty = all)
     * @return int[]|null  null = backend unavailable, use fallback
     */
    public function searchCardIds(string $query, array $attributesToSearchOn = []): ?array;

    public function indexCard(Card $card): void;

    public function deleteCard(Card $card): void;
}
