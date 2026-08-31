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

class CriteriaGroup
{
    public $criteria;
    public $conjunction;

    /**
     * @param array<int, Criteria|CriteriaGroup> $criteria
     */
    public function __construct(array $criteria, int $conjunction = Conjunction::GroupAnd)
    {
        $this->criteria = $criteria;
        $this->conjunction = $conjunction;
    }
}
