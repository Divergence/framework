<?php
namespace Divergence\IO\Database\Query;

class Update extends AbstractQuery
{
    public ?string $where;
    public ?array $set;
    public function set(array $set): Update
    {
        $this->set = $set;
        return $this;
    }
    public function where(string $where): Update
    {
        $this->where = $where;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('UPDATE `%s` SET %s WHERE %s', $this->table, join(',', $this->set), $this->where);
    }
}
