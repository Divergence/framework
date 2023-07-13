<?php

namespace Divergence\IO\Database\Query;

class Select extends AbstractQuery
{
    public ?string $where;
    public ?string $having;
    public ?string $limit;
    public ?string $order;
    public string $expression = '*';
    public bool $calcFoundRows = false;

    public function expression(string $expression): Select
    {
        $this->expression = $expression;
        return $this;
    }

    public function where(string $where): Select
    {
        $this->where = $where;
        return $this;
    }

    public function having(string $having): Select
    {
        $this->having = $having;
        return $this;
    }

    public function limit(string $limit): Select
    {
        if (!empty($limit)) {
            $this->limit = $limit;
        }
        return $this;
    }

    public function order(string $order): Select
    {
        if (!empty($order)) {
            $this->order = $order;
        }
        return $this;
    }

    public function calcFoundRows(): Select
    {
        $this->calcFoundRows = true;
        return $this;
    }

    public function __toString(): string
    {
        $expression = ($this->calcFoundRows ? 'SQL_CALC_FOUND_ROWS ' : '') . $this->expression;

        if (isset($this->tableAlias)) {
            $from = sprintf('`%s` AS `%s`', $this->table, $this->tableAlias);
        } else {
            $from = sprintf('`%s`', $this->table);
        }

        $limit = isset($this->limit) ? ' LIMIT '.$this->limit : '';
        $where = isset($this->where) ? ' WHERE ('.$this->where.')' : '';
        $having = isset($this->having) ? ' HAVING ('.$this->having.')' : '';
        $order = isset($this->order) ? ' ORDER BY '.$this->order : '';

        return sprintf('SELECT %s FROM %s %s %s %s %s', $expression, $from, $where, $having, $order, $limit);
    }
}
