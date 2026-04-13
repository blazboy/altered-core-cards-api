<?php

namespace App\Debug;

use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Tracks timing and metadata for each filter path during an API request.
 * Injected into filters via #[Required] setter — only active in dev.
 */
final class FilterProfiler
{
    /** @var array<string, array{path: string, duration_ms: float|null, ids: int|null, started_at: float}> */
    private array $events = [];

    private float $requestStart;

    public function __construct(private readonly Stopwatch $stopwatch)
    {
        $this->requestStart = microtime(true);
    }

    public function start(string $filter, string $path): void
    {
        $this->stopwatch->start("filter.$filter");
        $this->events[$filter] = [
            'path'        => $path,
            'duration_ms' => null,
            'ids'         => null,
            'started_at'  => microtime(true),
        ];
    }

    public function stop(string $filter, ?int $idCount = null): void
    {
        if (!isset($this->events[$filter])) {
            return;
        }

        try {
            $event = $this->stopwatch->stop("filter.$filter");
            $duration = $event->getDuration(); // ms
        } catch (\Throwable) {
            $duration = round((microtime(true) - $this->events[$filter]['started_at']) * 1000, 2);
        }

        $this->events[$filter]['duration_ms'] = $duration;
        $this->events[$filter]['ids']         = $idCount;
    }

    /** @return array<string, array{path: string, duration_ms: float|null, ids: int|null}> */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getTotalMs(): float
    {
        return round((microtime(true) - $this->requestStart) * 1000, 2);
    }

    public function reset(): void
    {
        $this->events      = [];
        $this->requestStart = microtime(true);
    }
}
