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

class Windows extends AbstractOperation
{
    /**
     * @return list<int|float>
     */
    public static function movingAverage(Collection $collection, callable $selector, int $windowSize): array
    {
        if ($windowSize < 1) {
            throw new InvalidArgumentException('Moving-average window size must be greater than zero.');
        }

        $values = static::numericValues($collection, $selector);

        if ($windowSize > count($values)) {
            return [];
        }

        $averages = [];
        $sum = array_sum(array_slice($values, 0, $windowSize));

        if (is_float($sum) && !is_finite($sum)) {
            throw new OverflowException('Moving average exceeded floating-point range.');
        }

        $averages[] = $sum / $windowSize;

        for ($index = $windowSize; $index < count($values); $index++) {
            $sum -= $values[$index - $windowSize];
            $sum += $values[$index];

            if (is_float($sum) && !is_finite($sum)) {
                throw new OverflowException('Moving average exceeded floating-point range.');
            }

            $averages[] = $sum / $windowSize;
        }

        return $averages;
    }

    /**
     * @template TResult
     * @param callable(list<object|array>): TResult $callback
     * @return list<TResult>
     */
    public static function rolling(Collection $collection, int $windowSize, callable $callback): array
    {
        if ($windowSize < 1) {
            throw new InvalidArgumentException('Rolling window size must be greater than zero.');
        }

        $records = array_values($collection->toArray());
        $results = [];

        for ($index = 0; $index + $windowSize <= count($records); $index++) {
            $results[] = $callback(array_slice($records, $index, $windowSize));
        }

        return $results;
    }
}
