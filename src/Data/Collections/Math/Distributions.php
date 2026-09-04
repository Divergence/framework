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

class Distributions extends AbstractOperation
{
    /**
     * @return list<array{min: float, max: float, count: int}>
     */
    public static function histogram(Collection $collection, callable $selector, int $bucketCount = 10): array
    {
        if ($bucketCount < 1) {
            throw new InvalidArgumentException('Histogram bucket count must be greater than zero.');
        }

        $values = static::numericValues($collection, $selector);

        if (!$values) {
            return [];
        }

        $minimumValue = min($values);
        $maximumValue = max($values);
        $minimum = (float) $minimumValue;
        $maximum = (float) $maximumValue;

        if ($minimumValue == $maximumValue) {
            return [['min' => $minimum, 'max' => $maximum, 'count' => count($values)]];
        }

        if ($minimum === $maximum) {
            throw new InvalidArgumentException('Histogram values exceed floating-point resolution.');
        }

        $range = $maximum - $minimum;
        $width = $range / $bucketCount;

        if (!is_finite($width) || $width <= 0) {
            throw new InvalidArgumentException('Histogram range must be finite.');
        }

        $histogram = [];

        for ($index = 0; $index < $bucketCount; $index++) {
            $bucket = [
                'min' => $minimum + ($range * ($index / $bucketCount)),
                'max' => $index === $bucketCount - 1
                    ? $maximum
                    : $minimum + ($range * (($index + 1) / $bucketCount)),
                'count' => 0,
            ];

            if ($bucket['min'] >= $bucket['max']) {
                throw new InvalidArgumentException('Histogram buckets exceed floating-point resolution.');
            }

            $histogram[] = $bucket;
        }

        foreach ($values as $value) {
            $index = min(
                $bucketCount - 1,
                (int) floor((($value - $minimum) / $range) * $bucketCount)
            );
            $histogram[$index]['count']++;
        }

        return $histogram;
    }

    /**
     * @return list<mixed>
     */
    public static function mode(Collection $collection, callable $selector): array
    {
        $frequencies = static::frequency($collection, $selector);

        if (!$frequencies) {
            return [];
        }

        $maximum = max(array_column($frequencies, 'count'));
        $modes = [];

        foreach ($frequencies as $frequency) {
            if ($frequency['count'] === $maximum) {
                $modes[] = $frequency['value'];
            }
        }

        return $modes;
    }

    /**
     * @return list<array{value: mixed, count: int}>
     */
    public static function frequency(Collection $collection, callable $selector): array
    {
        $frequencies = [];
        $frequencyIndexes = [];

        foreach ($collection->toArray() as $record) {
            $value = $selector($record);
            $lookupKey = serialize($value);

            if (isset($frequencyIndexes[$lookupKey])) {
                static::incrementFrequency($frequencies, $frequencyIndexes[$lookupKey]);
                continue;
            }

            $frequencyIndexes[$lookupKey] = count($frequencies);
            $frequencies[] = ['value' => $value, 'count' => 1];
        }

        return $frequencies;
    }

    /**
     * @return list<array{value: mixed, count: int}>
     */
    public static function countBy(Collection $collection, callable $selector): array
    {
        return static::frequency($collection, $selector);
    }

    /**
     * @param list<array{value: mixed, count: int}> $frequencies
     */
    protected static function incrementFrequency(array &$frequencies, int $index): void
    {
        $frequencies[$index]['count']++;
    }
}
