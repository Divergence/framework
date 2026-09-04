<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\IO\Database\Query;

class Delete extends AbstractQuery
{
    public ?string $where;

    public function where(string $where): Delete
    {
        $this->where = $where;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('DELETE FROM `%s` WHERE %s', $this->table, $this->where);
    }
}
