<?php

namespace App\Debug;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;

final class DbalProfilingStatement extends AbstractStatementMiddleware
{
    public function __construct(
        StatementInterface $statement,
        private readonly SqlProfiler $sqlProfiler,
        private readonly string $sql,
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $start  = microtime(true);
        $result = parent::execute();
        $this->sqlProfiler->record($this->sql, microtime(true) - $start);
        return $result;
    }
}
