<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models;

use BadMethodCallException;
use Exception;
use Divergence\Models\Factory\Instantiator;
use Divergence\Models\Factory\Getters\GetAll;
use Divergence\Models\Factory\Getters\GetAllByClass;
use Divergence\Models\Factory\Getters\GetAllByContext;
use Divergence\Models\Factory\Getters\GetAllByContextObject;
use Divergence\Models\Factory\Getters\GetAllByField;
use Divergence\Models\Factory\Getters\GetAllByQuery;
use Divergence\Models\Factory\Getters\GetAllByWhere;
use Divergence\Models\Factory\Getters\GetAllRecords;
use Divergence\Models\Factory\Getters\GetAllRecordsByWhere;
use Divergence\Models\Factory\Getters\GetByContext;
use Divergence\Models\Factory\Getters\GetByContextObject;
use Divergence\Models\Factory\Getters\GetByField;
use Divergence\Models\Factory\Getters\GetByHandle;
use Divergence\Models\Factory\Getters\GetByID;
use Divergence\Models\Factory\Getters\GetByQuery;
use Divergence\Models\Factory\Getters\GetByWhere;
use Divergence\Models\Factory\Getters\GetRecordByField;
use Divergence\Models\Factory\Getters\GetRecordByWhere;
use Divergence\Models\Factory\Getters\GetTableByQuery;
use Divergence\Models\Factory\Getters\GetUniqueHandle;
use Divergence\Models\Factory\Getters\ModelGetter;
use Divergence\Models\Factory\ModelMetadata;
use Divergence\IO\Database\Connections;
use PDO;

class Factory
{
    /**
     * @var array<string, object>
     */
    protected static $storages = [];

    /**
     * @var array<string, PDO>
     */
    protected static $connections = [];

    /**
     * @var array<string, Instantiator>
     */
    protected static $instantiators = [];

    /**
     * @var array<string, ModelMetadata>
     */
    protected static $metadata = [];

    /**
     * @var array<string, class-string>
     */
    protected $getterClasses = [];

    /**
     * @var array<string, ModelGetter>
     */
    protected $getters = [];

    /**
     * Fully-qualified model class name.
     *
     * @var string
     */
    protected $modelClass;

    /**
     * @var object
     */
    protected $storage;

    /**
     * @var PDO
     */
    protected $connection;

    /**
     * @var Instantiator
     */
    protected $instantiator;

    /**
     * @var ModelMetadata
     */
    protected $modelMetadata;

    /**
     * @param string $modelClass
     */
    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;

        $this->registerGetterClasses();
        $this->setModelMetadata();
        $this->configureConnection();
        $this->setInstantiator();
    }

    protected function registerGetterClasses(): void
    {
        $this->getterClasses = [];

        foreach ([
            GetByContextObject::class,
            GetByContext::class,
            GetByHandle::class,
            GetByID::class,
            GetByField::class,
            GetRecordByField::class,
            GetByWhere::class,
            GetRecordByWhere::class,
            GetByQuery::class,
            GetAllByClass::class,
            GetAllByContextObject::class,
            GetAllByContext::class,
            GetAllByField::class,
            GetAllByWhere::class,
            GetAll::class,
            GetAllRecords::class,
            GetAllByQuery::class,
            GetTableByQuery::class,
            GetAllRecordsByWhere::class,
            GetUniqueHandle::class,
        ] as $className) {
            $this->registerGetterClass($className);
        }
    }

    protected function registerGetterClass(string $className): void
    {
        $parts = explode('\\', $className);
        $getterName = strtolower(lcfirst(end($parts)));

        if (isset($this->getterClasses[$getterName])) {
            throw new Exception(sprintf('Getter method collision for %s', $getterName));
        }

        $this->getterClasses[$getterName] = $className;
    }

    public function __call(string $name, array $arguments)
    {
        $getterName = strtolower($name);

        if (!isset($this->getterClasses[$getterName])) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        if (!isset($this->getters[$getterName])) {
            $getterClass = $this->getterClasses[$getterName];
            $this->getters[$getterName] = new $getterClass($this);
        }

        return $this->getters[$getterName]->{$name}(...$arguments);
    }

    protected function setModelMetadata(): void
    {
        $modelClass = $this->modelClass;

        if (!isset(static::$metadata[$modelClass])) {
            static::$metadata[$modelClass] = ModelMetadata::get($modelClass);
        }

        $this->modelMetadata = static::$metadata[$modelClass];
    }

    protected function configureConnection(): void
    {
        if (Connections::$currentConnection === null) {
            Connections::setConnection();
        }

        $connectionLabel = Connections::$currentConnection;

        if (!isset(static::$storages[$connectionLabel])) {
            $storageClass = Connections::getConnectionType();
            static::$storages[$connectionLabel] = new $storageClass();
        }

        if (!isset(static::$connections[$connectionLabel])) {
            static::$connections[$connectionLabel] = static::$storages[$connectionLabel]->getConnection();
        }

        $this->storage = static::$storages[$connectionLabel];
        $this->connection = static::$connections[$connectionLabel];
    }

    protected function setInstantiator(): void
    {
        $modelClass = $this->modelClass;

        if (!isset(static::$instantiators[$modelClass])) {
            static::$instantiators[$modelClass] = new Instantiator($this->modelMetadata);
        }

        $this->instantiator = static::$instantiators[$modelClass];
    }

    /**
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->modelMetadata->getModelClass();
    }

    public function getStorage()
    {
        return $this->storage;
    }

    public function getGetterClasses(): array
    {
        return $this->getterClasses;
    }

    /**
     * Converts database record array to a model. Will attempt to use the record's Class field value to as the class to instantiate as or the name of this class if none is provided.
     *
     * @param array $record Database row as an array.
     * @return Model|null An instantiated ActiveRecord model from the provided data.
     */
    public function instantiateRecord($record)
    {
        return $this->instantiator->instantiateRecord($record);
    }

    /**
     * Converts an array of database records to a model corresponding to each record. Will attempt to use the record's Class field value to as the class to instantiate as or the name of this class if none is provided.
     *
     * @param array $record An array of database rows.
     * @return array<Model>|null An array of instantiated ActiveRecord models from the provided data.
     */
    public function instantiateRecords($records)
    {
        return $this->instantiator->instantiateRecords($records);
    }

    // TODO: make the handleField
    public function generateRandomHandle($length = 32)
    {
        $className = $this->modelClass;

        do {
            $handle = substr(md5(mt_rand(0, mt_getrandmax())), 0, $length);
        } while ($this->getByField($className::$handleField, $handle));

        return $handle;
    }

    /**
     * Resolve the active database connection on demand.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
