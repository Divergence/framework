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

class Deviations extends AbstractOperation
{
    public static function variance(Collection $collection, callable $selector): ?float
    {
        return static::varianceFromValues(static::numericValues($collection, $selector));
    }

    public static function stddev(Collection $collection, callable $selector): ?float
    {
        $variance = static::variance($collection, $selector);

        return $variance === null ? null : sqrt($variance);
    }

    /**
     * @return list<float>
     */
    public static function zScore(Collection $collection, callable $selector): array
    {
        return static::zScoresFromValues(static::numericValues($collection, $selector));
    }

    /**
     * @return list<object|array>
     */
    public static function outliers(Collection $collection, callable $selector, float $threshold = 3): array
    {
        if (!is_finite($threshold) || $threshold < 0) {
            throw new InvalidArgumentException('Outlier threshold cannot be negative.');
        }

        $records = array_values($collection->toArray());
        $values = array_map(
            static fn ($record): int|float => static::numericValue($selector($record)),
            $records
        );
        $scores = static::zScoresFromValues($values);
        $outliers = [];

        foreach ($scores as $index => $score) {
            if (abs($score) >= $threshold) {
                $outliers[] = $records[$index];
            }
        }

        return $outliers;
    }

    /**
     * @param list<int|float> $values
     * @return list<float>
     */
    protected static function zScoresFromValues(array $values): array
    {
        $variance = static::varianceFromValues($values);

        if ($variance === null) {
            return [];
        }

        if ($variance === 0.0) {
            return array_fill(0, count($values), 0.0);
        }

        $values = static::centeredValues($values);
        $average = array_sum($values) / count($values);
        $standardDeviation = sqrt($variance);

        return array_map(
            static fn (int|float $value): float => ($value - $average) / $standardDeviation,
            $values
        );
    }
}
