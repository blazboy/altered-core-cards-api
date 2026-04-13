<?php

namespace App\EventSubscriber;

use App\Debug\FilterProfiler;
use App\Debug\SqlProfiler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

final class DebugHeaderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FilterProfiler $profiler,
        private readonly SqlProfiler $sqlProfiler,
        private readonly KernelInterface $kernel,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->profiler->reset();
            $this->sqlProfiler->reset();
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->kernel->isDebug()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/cards')) {
            return;
        }

        $response = $event->getResponse();
        $events   = $this->profiler->getEvents();

        if (empty($events)) {
            return;
        }

        // One header per filter: filter:path:duration_ms:id_count
        $parts = [];
        foreach ($events as $filter => $data) {
            $ids      = $data['ids'] !== null ? $data['ids'] : '?';
            $duration = $data['duration_ms'] !== null ? round($data['duration_ms']) : '?';
            $parts[]  = sprintf('%s:%s:%sms:%s', $filter, $data['path'], $duration, $ids);
        }

        $response->headers->set('X-Debug-Filters', implode(',', $parts));
        $response->headers->set('X-Debug-Total-Ms', (string) round($this->profiler->getTotalMs()));
        $response->headers->set('X-Debug-Sql-Ms', (string) $this->sqlProfiler->getTotalMs());
        $response->headers->set('X-Debug-Sql-Count', (string) $this->sqlProfiler->getCount());
        $response->headers->set('Access-Control-Expose-Headers', 'X-Debug-Filters,X-Debug-Total-Ms,X-Debug-Sql-Ms,X-Debug-Sql-Count');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
