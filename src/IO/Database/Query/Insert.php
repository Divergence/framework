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
        if ($query = $this->materializeResolvedQuery()) {
            return (string) $query;
        }

        return $this->render();
    }

    protected function render(): string
    {
        return sprintf('INSERT INTO `%s` SET %s', $this->table, join(',', $this->set));
    }

    protected function splitAssignments(): array
    {
        $columns = [];
        $values = [];

        foreach ($this->set ?? [] as $assignment) {
            if (!preg_match('/^\s*(`[^`]+`)\s*=\s*(.+)\s*$/s', $assignment, $matches)) {
                throw new \RuntimeException(sprintf('Unsupported insert assignment: %s', $assignment));
            }

            $columns[] = $matches[1];
            $values[] = $matches[2];
        }

        return [$columns, $values];
    }
}
