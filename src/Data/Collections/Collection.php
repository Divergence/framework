<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections;

use Iterator;
use Countable;
use ArrayAccess;

/**
 * @template TRecord of object|array
 * @implements Iterator<array-key, TRecord>
 * @implements ArrayAccess<array-key, TRecord>
 */
class Collection implements Iterator, Countable, ArrayAccess, Indexing
{
    use Getters;

    public static $addHandler;
    public static $addManyHandler;
    public static $removeHandler;
    public static $removeManyHandler;
    public static $createIndexByFieldHandler;
    public static $hasIndexHandler;
    public static $updateIndexForModelHandler;
    public static $setIndexesHandler;
    public static $clearIndexesHandler;

    /** @var array<array-key, TRecord> */
    public array $Index = [];

    /** @var array<string, IndexedField> */
    public array $Indexes = [];

    /** @var array<int, TRecord> */
    public array $HashKeyIndex = [];

    public int $position = 0;

    /**
     * @param array<array-key, TRecord> $records
     * @param array<int, string> $indexes
     */
    public function __construct(array $records = [], array $indexes = [])
    {
        foreach ($indexes as $field) {
            $this->createIndexByField($field);
        }

        $this->addMany($records);
    }

    public function validate($record)
    {
        return true;
    }

    public function add($record)
    {
        $handler = static::$addHandler;
        $handler::handle($this, $record);
    }

    public function addMany(array $records)
    {
        $handler = static::$addManyHandler;
        $handler::handle($this, $records);
    }

    public function remove($record)
    {
        $handler = static::$removeHandler;
        $handler::handle($this, $record);
    }

    public function removeMany($records)
    {
        $handler = static::$removeManyHandler;
        $handler::handle($this, $records);
    }

    public function toArray()
    {
        return $this->Index;
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     */
    public function sum(callable $selector): int|float
    {
        return Math\Aggregates::sum($this, $selector);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     */
    public function median(callable $selector): int|float|null
    {
        return Math\Aggregates::median($this, $selector);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     */
    public function percentile(callable $selector, float $percentile): int|float|null
    {
        return Math\Aggregates::percentile($this, $selector, $percentile);
    }

    /**
     * Uses the nearest-rank definition.
     * Rank products within floating-point epsilon of an integer are treated as exact boundaries.
     *
     * @param callable(TRecord): (int|float) $selector
     */
    public function quantile(callable $selector, float $quantile): int|float|null
    {
        return Math\Aggregates::quantile($this, $selector, $quantile);
    }

    /**
     * Calculates population variance.
     *
     * @param callable(TRecord): (int|float) $selector
     */
    public function variance(callable $selector): ?float
    {
        return Math\Deviations::variance($this, $selector);
    }

    /**
     * Calculates population standard deviation.
     *
     * @param callable(TRecord): (int|float) $selector
     */
    public function stddev(callable $selector): ?float
    {
        return Math\Deviations::stddev($this, $selector);
    }

    /**
     * Buckets are half-open except for the final bucket, which includes its maximum.
     * A constant distribution returns one bucket.
     *
     * @param callable(TRecord): (int|float) $selector
     * @return list<array{min: float, max: float, count: int}>
     */
    public function histogram(callable $selector, int $bucketCount = 10): array
    {
        return Math\Distributions::histogram($this, $selector, $bucketCount);
    }

    /**
     * Returns every tied mode in first-seen order.
     *
     * @param callable(TRecord): mixed $selector
     * @return list<mixed>
     */
    public function mode(callable $selector): array
    {
        return Math\Distributions::mode($this, $selector);
    }

    /**
     * Calculates population covariance.
     *
     * @param callable(TRecord): (int|float) $firstSelector
     * @param callable(TRecord): (int|float) $secondSelector
     */
    public function covariance(callable $firstSelector, callable $secondSelector): ?float
    {
        return Math\Relationships::covariance($this, $firstSelector, $secondSelector);
    }

    /**
     * @param callable(TRecord): (int|float) $firstSelector
     * @param callable(TRecord): (int|float) $secondSelector
     */
    public function correlation(callable $firstSelector, callable $secondSelector): ?float
    {
        return Math\Relationships::correlation($this, $firstSelector, $secondSelector);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     * @return list<TRecord>
     */
    public function topK(callable $selector, int $count): array
    {
        return Math\Rankings::topK($this, $selector, $count);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     * @return list<TRecord>
     */
    public function bottomK(callable $selector, int $count): array
    {
        return Math\Rankings::bottomK($this, $selector, $count);
    }

    /**
     * Values are grouped by type and value so PHP array-key coercion cannot merge distinct values.
     *
     * @param callable(TRecord): mixed $selector
     * @return list<array{value: mixed, count: int}>
     */
    public function frequency(callable $selector): array
    {
        return Math\Distributions::frequency($this, $selector);
    }

    /**
     * @param callable(TRecord): mixed $selector
     * @return list<array{value: mixed, count: int}>
     */
    public function countBy(callable $selector): array
    {
        return Math\Distributions::countBy($this, $selector);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     * @return list<int|float>
     */
    public function movingAverage(callable $selector, int $windowSize): array
    {
        return Math\Windows::movingAverage($this, $selector, $windowSize);
    }

    /**
     * @template TResult
     * @param callable(list<TRecord>): TResult $callback
     * @return list<TResult>
     */
    public function rolling(int $windowSize, callable $callback): array
    {
        return Math\Windows::rolling($this, $windowSize, $callback);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     * @return list<float>
     */
    public function zScore(callable $selector): array
    {
        return Math\Deviations::zScore($this, $selector);
    }

    /**
     * @param callable(TRecord): (int|float) $selector
     * @return list<TRecord>
     */
    public function outliers(callable $selector, float $threshold = 3): array
    {
        return Math\Deviations::outliers($this, $selector, $threshold);
    }

    /* ### Implements IndexedFields Internally in the Collection ### */

    public function createIndexByField($field)
    {
        $handler = static::$createIndexByFieldHandler;
        $handler::handle($this, $field);
    }

    public function hasIndex($field): bool
    {
        $handler = static::$hasIndexHandler;
        return $handler::handle($this, $field);
    }

    public function updateIndexForModel($index, &$record)
    {
        $handler = static::$updateIndexForModelHandler;
        $handler::handle($this, $index, $record);
    }

    public function setIndexes(&$record)
    {
        $handler = static::$setIndexesHandler;
        $handler::handle($this, $record);
    }

    public function clearIndexes(&$record)
    {
        $handler = static::$clearIndexesHandler;
        $handler::handle($this, $record);
    }

    /* ### START implements Countable { ### */

    public function count(): int
    {
        return count($this->Index);
    }

    /* ### } END implements Countable ### */

    /* ### START implements Iterator { ### */

    public function current(): mixed
    {
        return $this->Index[$this->position] ?? null;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->Index[$this->position]);
    }

    /* ### } END implements Iterator ### */

    /* ### START implements ArrayAccess { ### */

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            $this->add($value);
        } elseif ($this->validate($value)) {
            if (isset($this->Index[$offset])) {
                $position = $this->position;
                $this->offsetUnset($offset);
                array_splice($this->Index, $offset, 0, [$value]);
                $this->position = $position;
            } else {
                $this->Index[$offset] = $value;
            }

            $this->setIndexes($value);
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->Index[$offset]);
    }

    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->Index[$offset])) {
            $record = $this->Index[$offset];
            $recordKey = RecordKey::get($record);
            unset($this->HashKeyIndex[$recordKey]);
            $this->clearIndexes($record);
            array_splice($this->Index, $offset, 1);

            if ($this->position > $offset) {
                --$this->position;
            }
        }
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($offset < 0) {
            $offset += $this->count();
        }
        return $this->Index[$offset] ?? null;
    }

    /* ### } END implements ArrayAccess ### */
}
