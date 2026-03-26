<?php

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
