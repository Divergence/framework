<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Expr;

class Criteria
{
    public $key;
    public $value;
    public $operator;
    public $rawOperator;

    public function __construct(string $key, $value = null, int $operator = CriteriaType::Equal, ?string $rawOperator = null)
    {
        $this->key = $key;
        $this->value = $value;
        $this->operator = $operator;
        $this->rawOperator = $rawOperator;
    }
}
