<?php

namespace Divergence\IO\Database\Query;

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

    abstract public function __toString(): string;
}
