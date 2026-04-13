<?php

namespace App\Debug;

/**
 * Collects SQL query timings for the debug panel.
 */
final class SqlProfiler
{
    /** @var array<array{sql: string, duration_ms: float}> */
    private array $queries = [];

    public function record(string $sql, float $durationSeconds): void
    {
        $this->queries[] = [
            'sql'         => $sql,
            'duration_ms' => round($durationSeconds * 1000, 2),
        ];
    }

    public function getTotalMs(): float
    {
        return round(array_sum(array_column($this->queries, 'duration_ms')), 2);
    }

    public function getCount(): int
    {
        return count($this->queries);
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
