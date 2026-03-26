<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Factory;

use ReflectionClass;

class Instantiator
{
    /**
     * @var ModelMetadata
     */
    protected $metadata;

    /**
     * @var EventBinder
     */
    protected $eventBinder;

    /**
     * @var PrototypeRegistry
     */
    protected $prototypeRegistry;

    /**
     * @param string $modelClass
     */
    public function __construct(ModelMetadata $metadata)
    {
        $this->metadata = $metadata;
        $this->eventBinder = new EventBinder();
        $this->prototypeRegistry = new PrototypeRegistry();
    }

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
     * @param array $record
     * @return \Divergence\Models\Model|null
     */
    public function instantiateRecord($record)
    {
        return $this->instantiateModel($record);
    }

    /**
     * @param array $records
     * @return array<\Divergence\Models\Model>|null
     */
    public function instantiateRecords($records)
    {
        foreach ($records as &$record) {
            $record = $this->instantiateModel($record);
        }

        return $records;
    }

    protected function instantiateModel($record)
    {
        $className = $this->getRecordClass($record);

        if (!$record) {
            return null;
        }

        $prototype = $this->prototypeRegistry->get($className, function () use ($className) {
            $model = (new ReflectionClass($className))->newInstanceWithoutConstructor();

            return $this->eventBinder->bindPrototype($model);
        });

        $model = clone $prototype;

        return $this->eventBinder->bindRecord($model, $record);
    }
}
