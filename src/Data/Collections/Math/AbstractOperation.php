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

abstract class AbstractOperation
{
    /**
     * @param callable(object|array): (int|float) $selector
     * @return list<int|float>
     */
    protected static function numericValues(Collection $collection, callable $selector): array
    {
        return array_map(
            static fn ($record): int|float => static::numericValue($selector($record)),
            array_values($collection->toArray())
        );
    }

    protected static function numericValue($value): int|float
    {
        if ((!is_int($value) && !is_float($value)) || (is_float($value) && !is_finite($value))) {
            throw new InvalidArgumentException('Math selectors must return finite integers or floats.');
        }

        return $value;
    }

    /**
     * @param list<int|float> $values
     * @return list<int|float>
     */
    protected static function centeredValues(array $values): array
    {
        if (!$values) {
            return [];
        }

        $origin = $values[0];

        return array_map(
            static fn (int|float $value): int|float => $value - $origin,
            $values
        );
    }

    /**
     * @param list<int|float> $values
     */
    protected static function varianceFromValues(array $values): ?float
    {
        if (!$values) {
            return null;
        }

        $values = static::centeredValues($values);
        $average = array_sum($values) / count($values);
        $variance = array_sum(array_map(
            static fn (int|float $value): int|float => ($value - $average) ** 2,
            $values
        )) / count($values);

        if (!is_finite($variance)) {
            throw new OverflowException('Variance exceeded floating-point range.');
        }

        return $variance;
    }
}
