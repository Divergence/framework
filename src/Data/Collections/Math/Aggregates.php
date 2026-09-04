<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections\Math;

use Divergence\Data\Collections\Collection;
use InvalidArgumentException;
use OverflowException;

class Aggregates extends AbstractOperation
{
    public static function sum(Collection $collection, callable $selector): int|float
    {
        $sum = array_sum(static::numericValues($collection, $selector));

        if (is_float($sum) && !is_finite($sum)) {
            throw new OverflowException('Sum exceeded floating-point range.');
        }

        return $sum;
    }

    public static function median(Collection $collection, callable $selector): int|float|null
    {
        $values = static::numericValues($collection, $selector);

        if (!$values) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2) {
            return $values[$middle];
        }

        $lower = $values[$middle - 1];
        $upper = $values[$middle];
        $sum = $lower + $upper;

        if (!is_float($sum) || is_finite($sum)) {
            return $sum / 2;
        }

        return ($lower / 2) + ($upper / 2);
    }

    public static function percentile(
        Collection $collection,
        callable $selector,
        float $percentile
    ): int|float|null {
        return static::quantile($collection, $selector, $percentile / 100);
    }

    public static function quantile(
        Collection $collection,
        callable $selector,
        float $quantile
    ): int|float|null {
        if (!is_finite($quantile) || $quantile < 0 || $quantile > 1) {
            throw new InvalidArgumentException('Quantile must be between 0 and 1.');
        }

        $values = static::numericValues($collection, $selector);

        if (!$values) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $rank = $quantile * count($values);
        $tolerance = PHP_FLOAT_EPSILON * max(1, abs($rank));
        $index = max(0, (int) ceil($rank - $tolerance) - 1);

        return $values[$index];
    }
}
