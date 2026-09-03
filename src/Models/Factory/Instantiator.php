<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @phan-file-suppress PhanTypeMismatchReturn
 */

namespace Divergence\Models\Factory;

use ReflectionClass;
use Divergence\Models\Model;
use Divergence\Models\Mapping\InMemoryIndexing;
use Divergence\Models\Collections\RecordCollection;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 */
class Instantiator
{
    /**
     * @var ModelMetadata<TModel>
     */
    protected $metadata;

    /**
     * @var EventBinder
     */
    protected $eventBinder;

    /**
     * @var RecordCollection<TModel>|null
     */
    protected $Collection;

    /**
     * @var InMemoryIndexing|null
     */
    protected $indexingConfig;

    /**
     * @param string $modelClass
     */
    /**
     * @param ModelMetadata<TModel> $metadata
     */
    public function __construct(ModelMetadata $metadata)
    {
        $this->metadata = $metadata;
        $this->eventBinder = new EventBinder();

        $modelClass = $this->metadata->getModelClass();
        $attributes = (new ReflectionClass($modelClass))->getAttributes(InMemoryIndexing::class);

        if ($attributes) {
            $this->indexingConfig = $attributes[0]->newInstance();
            $this->instantiateCollection();
        }
    }

    /**
     * @param array<string, mixed> $record
     * @return class-string<TModel>
     */
    protected function getRecordClass($record)
    {
        $className = $this->metadata->getModelClass();

        if (!$this->metadata->hasClassField()) {
            return $className;
        }

        $columnName = $this->metadata->getClassColumnName();

        if (!empty($record[$columnName]) && is_subclass_of($record[$columnName], $className)) {
            return $record[$columnName];
        }

        return $className;
    }

    /**
     * @param array<string, mixed> $record
     * @return TModel
     */
    public function instantiatePhantomRecord($record = [])
    {
        $className = $this->getRecordClass($record);
        $prototype = $this->createPrototype($className);
        $model = clone $prototype;

        $model = $this->eventBinder->bindRecord($model, [], false, true);
        $model->setFields($record);

        return $model;
    }

    /**
     * @param array<string, mixed>|false|null $record
     * @return TModel|null
     */
    public function instantiateRecord($record)
    {
        if ($record === false || $record === null) {
            return null;
        }

        return $this->instantiateModel($record, false);
    }

    /**
     * @param array<array-key, array<string, mixed>>|array<string, array<string, mixed>> $records
     * @return array<array-key, TModel>|array<string, TModel>|RecordCollection<TModel>
     */
    public function instantiateRecords($records)
    {
        $Collection = $this->Collection;

        if ($Collection) {
            $this->instantiateCollection();
        }

        foreach ($records as $key => $record) {
            $records[$key] = $record = $this->instantiateModel($record);

            if ($Collection) {
                $Collection->add($record);
            }
        }

        return $Collection ?: $records;
    }

    protected function instantiateCollection(): void
    {
        $modelClass = $this->metadata->getModelClass();
        $this->Collection = new RecordCollection([], $this->indexingConfig->indexes, $modelClass);
    }

    /**
     * @param array<string, mixed> $record
     * @return TModel
     */
    protected function instantiateModel(array $record, bool $phantom = false)
    {
        $className = $this->getRecordClass($record);

        $prototype = $this->createPrototype($className);

        $model = clone $prototype;

        return $this->eventBinder->bindRecord($model, $record, false, $phantom);
    }

    protected function createPrototype(string $className)
    {
        $model = (new ReflectionClass($className))->newInstanceWithoutConstructor();

        return $this->eventBinder->initPrototype($model);
    }
}
