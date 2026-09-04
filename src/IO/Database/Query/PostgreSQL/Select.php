<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\IO\Database\Query\PostgreSQL;

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
