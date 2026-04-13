<?php

namespace App\Search;

use App\Service\MeilisearchService;

final class MeilisearchBackend implements SearchBackendInterface
{
    public function __construct(private readonly MeilisearchService $meilisearch) {}

    public function searchCardIds(string $query, array $filters = []): ?array
    {
        try {
            return $this->meilisearch->searchIds($query, $filters);
        } catch (\Throwable) {
            return null;
        }
    }
}
