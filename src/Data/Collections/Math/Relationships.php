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
use OverflowException;

class Relationships extends AbstractOperation
{
    public static function covariance(
        Collection $collection,
        callable $firstSelector,
        callable $secondSelector
    ): ?float {
        [$firstValues, $secondValues] = static::pairedValues($collection, $firstSelector, $secondSelector);

        return static::covarianceFromValues($firstValues, $secondValues);
    }

    public static function correlation(
        Collection $collection,
        callable $firstSelector,
        callable $secondSelector
    ): ?float {
        [$firstValues, $secondValues] = static::pairedValues($collection, $firstSelector, $secondSelector);
        $covariance = static::covarianceFromValues($firstValues, $secondValues);
        $firstVariance = static::varianceFromValues($firstValues);
        $secondVariance = static::varianceFromValues($secondValues);

        if ($covariance === null || !$firstVariance || !$secondVariance) {
            return null;
        }

        $correlation = $covariance / (sqrt($firstVariance) * sqrt($secondVariance));

        return max(-1.0, min(1.0, $correlation));
    }

    /**
     * @return array{list<int|float>, list<int|float>}
     */
    protected static function pairedValues(
        Collection $collection,
        callable $firstSelector,
        callable $secondSelector
    ): array {
        $firstValues = [];
        $secondValues = [];

        foreach ($collection->toArray() as $record) {
            $firstValues[] = static::numericValue($firstSelector($record));
            $secondValues[] = static::numericValue($secondSelector($record));
        }

        return [$firstValues, $secondValues];
    }

    /**
     * @param list<int|float> $firstValues
     * @param list<int|float> $secondValues
     */
    protected static function covarianceFromValues(array $firstValues, array $secondValues): ?float
    {
        if (!$firstValues) {
            return null;
        }

        $firstValues = static::centeredValues($firstValues);
        $secondValues = static::centeredValues($secondValues);
        $count = count($firstValues);
        $firstAverage = array_sum($firstValues) / $count;
        $secondAverage = array_sum($secondValues) / $count;
        $covariance = array_sum(array_map(
            static fn (int|float $first, int|float $second): int|float =>
                ($first - $firstAverage) * ($second - $secondAverage),
            $firstValues,
            $secondValues
        )) / $count;

        if (!is_finite($covariance)) {
            throw new OverflowException('Covariance exceeded floating-point range.');
        }

        return $covariance;
    }
}
