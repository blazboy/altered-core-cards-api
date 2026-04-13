<?php

namespace App\Debug;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class DbalProfilingDriver extends AbstractDriverMiddleware
{
    public function __construct(
        \Doctrine\DBAL\Driver $driver,
        private readonly SqlProfiler $sqlProfiler,
    ) {
        parent::__construct($driver);
    }

    public function connect(#[\SensitiveParameter] array $params): ConnectionInterface
    {
        return new DbalProfilingConnection(
            parent::connect($params),
            $this->sqlProfiler,
        );
    }
}
