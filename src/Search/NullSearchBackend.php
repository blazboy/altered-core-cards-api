<?php

namespace App\Search;

use App\Entity\Card;

/**
 * No-op backend — always returns null to trigger the PostgreSQL LIKE fallback.
 * Used when no search engine is configured (MEILISEARCH_ENABLED=false).
 */
final class NullSearchBackend implements SearchBackendInterface
{
    public function searchCardIds(string $query, array $attributesToSearchOn = []): ?array
    {
        return null;
    }

    public function indexCard(Card $card): void {}

    public function deleteCard(Card $card): void {}
}
