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

use Divergence\IO\Database\Connections;

abstract class AbstractQuery
{
    public string $table;
    public string $tableAlias;

    /** @return static */
    public function setTable(string $table): AbstractQuery
    {
        $this->table = $table;
        return $this;
    }

    /** @return static */
    public function setTableAlias(string $alias): AbstractQuery
    {
        $this->tableAlias = $alias;
        return $this;
    }

    protected function materializeResolvedQuery(): ?AbstractQuery
    {
        $queryClass = Connections::getQueryClass(static::class);

        if ($queryClass === static::class) {
            return null;
        }

        $query = new $queryClass();

        foreach (get_object_vars($this) as $property => $value) {
            $query->$property = $value;
        }

        return $query;
    }

    abstract public function __toString(): string;
}
