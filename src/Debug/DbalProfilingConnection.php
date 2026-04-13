<?php

namespace App\Debug;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;

final class DbalProfilingConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        ConnectionInterface $connection,
        private readonly SqlProfiler $sqlProfiler,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): StatementInterface
    {
        return new DbalProfilingStatement(
            parent::prepare($sql),
            $this->sqlProfiler,
            $sql,
        );
    }

    public function query(string $sql): Result
    {
        $start  = microtime(true);
        $result = parent::query($sql);
        $this->sqlProfiler->record($sql, microtime(true) - $start);
        return $result;
    }

    public function exec(string $sql): int|string
    {
        $start  = microtime(true);
        $result = parent::exec($sql);
        $this->sqlProfiler->record($sql, microtime(true) - $start);
        return $result;
    }
}
