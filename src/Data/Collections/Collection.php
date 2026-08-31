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
