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

class CriteriaType
{
    const Equal = 1;
    const NotEqual = 2;
    const GreaterThan = 3;
    const GreaterThanOrEqual = 4;
    const LessThan = 5;
    const LessThanOrEqual = 6;
    const Like = 7;
    const NotLike = 8;
    const In = 9;
    const NotIn = 10;
    const Nulled = 11;
    const NotNulled = 12;
    const Exists = 13;
    const NotExists = 14;
    const Raw = 15;
    const FieldEqual = 16;
    const FieldNotEqual = 17;
    const FieldGreaterThan = 18;
    const FieldGreaterThanOrEqual = 19;
    const FieldLessThan = 20;
    const FieldLessThanOrEqual = 21;
}
