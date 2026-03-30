<?php

namespace App\State;

use ApiPlatform\State\Pagination\PaginatorInterface;

/**
 * Wraps a PaginatorInterface and replaces getTotalItems() / getLastPage()
 * with a pre-computed cached value, so no COUNT query is issued.
 */
final class CachedTotalPaginator implements PaginatorInterface, \IteratorAggregate
{
    public function __construct(
        private readonly PaginatorInterface $inner,
        private readonly float $cachedTotal,
    ) {}

    public function getTotalItems(): float
    {
        return $this->cachedTotal;
    }

    public function getLastPage(): float
    {
        $perPage = $this->inner->getItemsPerPage();

        return $perPage > 0 ? (float) ceil($this->cachedTotal / $perPage) : 1.0;
    }

    public function getCurrentPage(): float
    {
        return $this->inner->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return $this->inner->getItemsPerPage();
    }

    public function count(): int
    {
        return $this->inner->count();
    }

    public function getIterator(): \Traversable
    {
        return $this->inner->getIterator();
    }
}
