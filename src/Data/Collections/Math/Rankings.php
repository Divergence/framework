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

class Rankings extends AbstractOperation
{
    /**
     * @return list<object|array>
     */
    public static function topK(Collection $collection, callable $selector, int $count): array
    {
        return static::rankedRecords($collection, $selector, $count, true);
    }

    /**
     * @return list<object|array>
     */
    public static function bottomK(Collection $collection, callable $selector, int $count): array
    {
        return static::rankedRecords($collection, $selector, $count, false);
    }

    /**
     * @return list<object|array>
     */
    protected static function rankedRecords(
        Collection $collection,
        callable $selector,
        int $count,
        bool $descending
    ): array {
        if ($count < 0) {
            throw new InvalidArgumentException('Ranked result count cannot be negative.');
        }

        if ($count === 0) {
            return [];
        }

        $ranked = [];

        $position = 0;

        foreach ($collection->toArray() as $record) {
            $ranked[] = [
                'record' => $record,
                'value' => static::numericValue($selector($record)),
                'position' => $position,
            ];
            $position++;
        }

        usort($ranked, static function (array $first, array $second) use ($descending): int {
            $comparison = $first['value'] <=> $second['value'];

            if ($comparison === 0) {
                return $first['position'] <=> $second['position'];
            }

            return $descending ? -$comparison : $comparison;
        });

        return array_column(array_slice($ranked, 0, $count), 'record');
    }
}
