<?php

namespace App\Search;

use App\Entity\Card;
use App\Service\MeilisearchService;
use Psr\Log\LoggerInterface;

final class MeilisearchBackend implements SearchBackendInterface
{
    public function __construct(
        private readonly MeilisearchService $meilisearch,
        private readonly LoggerInterface $logger,
    ) {}

    public function searchCardIds(string $query, array $attributesToSearchOn = []): ?array
    {
        try {
            return $this->meilisearch->searchIds($query, $attributesToSearchOn);
        } catch (\Throwable $e) {
            $this->logger->error('Meilisearch search failed, falling back to LIKE', [
                'error'                => $e->getMessage(),
                'query'                => $query,
                'attributesToSearchOn' => $attributesToSearchOn,
            ]);
            return null;
        }
    }

    public function indexCard(Card $card): void
    {
        try {
            $this->meilisearch->indexCard($card);
        } catch (\Throwable $e) {
            $this->logger->error('Meilisearch indexCard failed', ['error' => $e->getMessage(), 'card' => $card->getId()]);
        }
    }

    public function deleteCard(Card $card): void
    {
        try {
            $this->meilisearch->deleteCard($card);
        } catch (\Throwable $e) {
            $this->logger->error('Meilisearch deleteCard failed', ['error' => $e->getMessage(), 'card' => $card->getId()]);
        }
    }
}
