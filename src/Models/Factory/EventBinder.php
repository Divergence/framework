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

use ReflectionProperty;

class EventBinder
{
    /**
     * @var array<string, array<string, ReflectionProperty>>
     */
    protected static $propertyCache = [];

    protected function getProperty($model, string $property): ReflectionProperty
    {
        $className = get_class($model);

        if (!isset(static::$propertyCache[$className][$property])) {
            static::$propertyCache[$className][$property] = new ReflectionProperty($model, $property);
        }

        return static::$propertyCache[$className][$property];
    }

    protected function setProperty($model, string $property, $value): void
    {
        $this->getProperty($model, $property)->setValue($model, $value);
    }

    protected function synchronizeMappedProperties($model): void
    {
        if (method_exists($model, 'initializeAttributeFields')) {
            $model->initializeAttributeFields();
        }
    }

    public function bindPrototype($model)
    {
        $className = get_class($model);

        $className::init();

        $this->setProperty($model, '_record', []);
        $this->setProperty($model, '_convertedValues', []);
        $this->setProperty($model, '_validator', null);
        $this->setProperty($model, '_validationErrors', []);
        $this->setProperty($model, '_originalValues', []);
        $this->setProperty($model, '_isDirty', false);
        $this->setProperty($model, '_isPhantom', true);
        $this->setProperty($model, '_wasPhantom', true);
        $this->setProperty($model, '_isValid', true);
        $this->setProperty($model, '_isNew', false);
        $this->setProperty($model, '_isUpdated', false);

        if (property_exists($model, '_relatedObjects')) {
            $this->setProperty($model, '_relatedObjects', []);
        }

        $this->synchronizeMappedProperties($model);

        return $model;
    }

    public function bindRecord($model, array $record = [], bool $isDirty = false, ?bool $isPhantom = null)
    {
        $className = get_class($model);
        $isPhantom = isset($isPhantom) ? $isPhantom : empty($record);

        if ($className::fieldExists('Class')) {
            $columnName = $className::getColumnName('Class');

            if (empty($record[$columnName])) {
                $record[$columnName] = $className;
            }
        }

        if (property_exists($model, '_suppressRecordSynchronization')) {
            $this->setProperty($model, '_suppressRecordSynchronization', true);
        }

        $this->setProperty($model, '_record', $record);

        if (property_exists($model, '_suppressRecordSynchronization')) {
            $this->setProperty($model, '_suppressRecordSynchronization', false);
        }
        $this->setProperty($model, '_convertedValues', []);
        $this->setProperty($model, '_validator', null);
        $this->setProperty($model, '_validationErrors', []);
        $this->setProperty($model, '_originalValues', []);
        $this->setProperty($model, '_isDirty', $isPhantom || $isDirty);
        $this->setProperty($model, '_isPhantom', $isPhantom);
        $this->setProperty($model, '_wasPhantom', $isPhantom);
        $this->setProperty($model, '_isValid', true);
        $this->setProperty($model, '_isNew', false);
        $this->setProperty($model, '_isUpdated', false);

        if (property_exists($model, '_relatedObjects')) {
            $this->setProperty($model, '_relatedObjects', []);
        }

        $this->synchronizeMappedProperties($model);

        return $model;
    }
}
