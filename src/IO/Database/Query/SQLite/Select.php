<?php

namespace Divergence\IO\Database\Query\SQLite;

use Divergence\IO\Database\Query\Select as BaseSelect;

class Select extends BaseSelect
{
    protected function render(): string
    {
        $calcFoundRows = $this->calcFoundRows;
        $this->calcFoundRows = false;

        try {
            return parent::render();
        } finally {
            $this->calcFoundRows = $calcFoundRows;
        }
    }
}
