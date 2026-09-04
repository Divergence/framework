<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
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
