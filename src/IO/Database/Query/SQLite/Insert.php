<?php

namespace Divergence\IO\Database\Query\SQLite;

use Divergence\IO\Database\Query\Insert as BaseInsert;

class Insert extends BaseInsert
{
    protected function render(): string
    {
        [$columns, $values] = $this->splitAssignments();

        return sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            join(',', $columns),
            join(',', $values)
        );
    }
}
