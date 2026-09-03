<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Collections;

use Exception;
use Divergence\Models\ActiveRecord;
use Divergence\Data\Collections\RecordKey;
use Divergence\IO\Database\Connections;
use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\Events\AddMany;
use Divergence\Data\Collections\Events\RemoveMany;
use Divergence\Data\Collections\Indexing\HasIndex;
use Divergence\Models\Collections\Events\Add;
use Divergence\Models\Collections\Events\Remove;
use Divergence\Models\Collections\Indexing\CreateIndexByField;
use Divergence\Models\Collections\Indexing\UpdateIndexForModel;
use Divergence\Models\Collections\Indexing\SetIndexes;
use Divergence\Models\Collections\Indexing\ClearIndexes;
use Divergence\Models\Collections\Factory\Factory;

/**
 * @template TModel of ActiveRecord
 * @extends \Divergence\Data\Collections\Collection<TModel>
 */
class RecordCollection extends Collection
{
    public static $addHandler = Add::class;
    public static $addManyHandler = AddMany::class;
    public static $removeHandler = Remove::class;
    public static $removeManyHandler = RemoveMany::class;
    public static $createIndexByFieldHandler = CreateIndexByField::class;
    public static $hasIndexHandler = HasIndex::class;
    public static $updateIndexForModelHandler = UpdateIndexForModel::class;
    public static $setIndexesHandler = SetIndexes::class;
    public static $clearIndexesHandler = ClearIndexes::class;

    public static function Factory(): Factory
    {
        return new Factory();
    }

    /** @var class-string<TModel>|null */
    public $recordClassName;

    /**
     * @param array<array-key, TModel> $records
     * @param array<int, string> $indexes
     * @param class-string<TModel>|null $recordClassName
     */
    public function __construct(array $records = [], array $indexes = [], $recordClassName = null)
    {
        $this->recordClassName = $recordClassName;

        if (!$this->recordClassName && count($records)) {
            $this->recordClassName = get_class(reset($records));
        }

        foreach ($indexes as $field) {
            $this->createIndexByField($field);
        }

        $this->addMany($records);
    }

    public function validate($record)
    {
        if (!$this->recordClassName) {
            $this->recordClassName = get_class($record);
        }

        return is_a($record, $this->recordClassName);
    }

    public function isDirty()
    {
        if (count($this->Index)) {
            foreach ($this->Index as $Model) {
                if ($Model->isDirty) {
                    return true;
                }
            }
        }
        return false;
    }

    public function saveWithTransaction(bool $deep = true)
    {
        if (count($this->Index) === 0) {
            return;
        }

        $connection = Connections::getConnection();
        $models = $this->Index;
        $states = array_map(fn ($Model) => clone $Model, $models);

        try {
            $connection->beginTransaction();

            foreach ($this->Index as $Model) {
                if ($Model->isDirty || $Model->isPhantom) {
                    $this->remove($Model);
                    $Model->save($deep);
                    $this->add($Model);
                }
            }

            return $connection->commit();
        } catch (Exception $exception) {
            $connection->rollBack();

            foreach ($this->Index as $Model) {
                $this->clearIndexes($Model);
            }

            $this->Index = $this->HashKeyIndex = [];

            foreach ($models as $key => $Model) {
                $Model->restoreState($states[$key]);
            }

            $this->addMany($models);
            throw $exception;
        }
    }

    public function save(bool $deep = true): void
    {
        foreach ($this->Index as $Model) {
            if ($Model->isDirty || $Model->isPhantom) {
                $this->remove($Model);
                $Model->save($deep);
                $this->add($Model);
            }
        }
    }

    public function current(): ?ActiveRecord
    {
        return $this->Index[$this->position] ?? null;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (isset($this->Index[$offset])) {
            $Model = $this->Index[$offset];
            $modelKey = RecordKey::get($Model);
            unset($this->HashKeyIndex[$modelKey]);
            $this->clearIndexes($Model);
            array_splice($this->Index, $offset, 1);

            if ($this->position > $offset) {
                --$this->position;
            }
        }
    }

    public function offsetGet(mixed $offset): ?ActiveRecord
    {
        if ($offset < 0) {
            $offset += $this->count();
        }
        return $this->Index[$offset] ?? null;
    }
}
