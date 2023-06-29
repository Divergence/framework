<?php

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
