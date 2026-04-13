<?php

namespace App\Search;

/**
 * No-op backend — always returns null to trigger the PostgreSQL LIKE fallback.
 * Used when no search engine is configured.
 */
final class NullSearchBackend implements SearchBackendInterface
{
    public function searchCardIds(string $query, array $filters = []): ?array
    {
        return null;
    }
}
