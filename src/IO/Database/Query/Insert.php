<?php
namespace Divergence\IO\Database\Query;

class Insert extends AbstractQuery
{
    public ?array $set;
    public function set(array $set): Insert
    {
        $this->set = $set;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('INSERT INTO `%s` SET %s', $this->table, join(',', $this->set));
    }
}
