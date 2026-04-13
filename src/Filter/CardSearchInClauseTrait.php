<?php

namespace App\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Safely applies a large IN clause of integer card IDs without hitting
 * PostgreSQL's 65535 bind-parameter limit.
 *
 * IDs are cast to int and inlined directly into the SQL expression.
 * This is safe because they are guaranteed integers from card_search.
 */
trait CardSearchInClauseTrait
{
    private function applyIdInClause(QueryBuilder $qb, string $alias, array $ids): void
    {
        if (empty($ids)) {
            $qb->andWhere('1 = 0');
            return;
        }

        $intIds = implode(',', array_map('intval', $ids));
        $qb->andWhere("$alias.id IN ($intIds)");
    }
}
