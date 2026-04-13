<?php

namespace App\Debug;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class DbalProfilingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SqlProfiler $sqlProfiler) {}

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new DbalProfilingDriver($driver, $this->sqlProfiler);
    }
}
