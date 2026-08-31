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

class Conjunction
{
    const GroupAnd = 1;
    const GroupOr = 2;
    const GroupNotAnd = 3;
    const GroupNotOr = 4;
}
