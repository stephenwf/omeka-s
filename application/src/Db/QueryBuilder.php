<?php

namespace Omeka\Db;

use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

class QueryBuilder extends DoctrineQueryBuilder
{
    protected $index = 0;

    /**
     * Create a unique named parameter for the query builder and bind a value to
     * it.
     *
     * @param mixed $value The value to bind
     * @param string $prefix The placeholder prefix
     * @return string The placeholder
     */
    public function createNamedParameter($value, $prefix = 'omeka_'
    ) {
        $placeholder = $prefix . $this->index++;
        $qb->setParameter($placeholder, $value);
        return ":$placeholder";
    }
}
