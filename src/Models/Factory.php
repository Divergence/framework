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

use Exception;
use Divergence\Helpers\Util;
use Divergence\Models\Factory\Instantiator;
use Divergence\Models\Factory\ModelMetadata;
use Divergence\IO\Database\Connections;
use Divergence\IO\Database\Query\Select;
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

        if (!isset(static::$metadata[$modelClass])) {
            static::$metadata[$modelClass] = ModelMetadata::get($modelClass);
        }

        $this->modelMetadata = static::$metadata[$modelClass];

        if (Connections::$currentConnection === null) {
            Connections::setConnection();
        }

        $connectionLabel = Connections::$currentConnection;
        $storageClass = Connections::getConnectionType();

        if (!isset(static::$storages[$connectionLabel])) {
            static::$storages[$connectionLabel] = new $storageClass();
        }

        if (!isset(static::$connections[$connectionLabel])) {
            static::$connections[$connectionLabel] = static::$storages[$connectionLabel]->getConnection();
        }

        if (!isset(static::$instantiators[$modelClass])) {
            static::$instantiators[$modelClass] = new Instantiator($this->modelMetadata);
        }

        $this->storage = static::$storages[$connectionLabel];
        $this->connection = static::$connections[$connectionLabel];
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

    protected function getColumnName($field)
    {
        return $this->modelMetadata->getColumnName($field);
    }

    protected function mapFieldOrder($order)
    {
        $className = $this->modelClass;

        return $className::mapFieldOrder($order);
    }

    protected function mapConditions($conditions)
    {
        $className = $this->modelClass;

        return $className::mapConditions($conditions);
    }

    protected function fieldExists($field): bool
    {
        return $this->modelMetadata->fieldExists($field);
    }

    protected function getHandleExceptionCallback(): array
    {
        return $this->modelMetadata->getHandleExceptionCallback();
    }

    protected function getPrimaryKeyName(): string
    {
        return $this->modelMetadata->getPrimaryKey();
    }

    protected function getHandleFieldName(): string
    {
        return $this->modelMetadata->getHandleField();
    }

    protected function getTableName(): string
    {
        return $this->modelMetadata->getTableName();
    }

    protected function getRootClass(): string
    {
        return $this->modelMetadata->getRootClass();
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

    /**
     * Uses ContextClass and ContextID to get an object.
     * Quick way to attach things to other objects in a one-to-one relationship
     *
     * @param array $record An array of database rows.
     * @return Model|null An array of instantiated ActiveRecord models from the provided data.
     */
    public function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->getByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }

    /**
    * Same as getByContextObject but this method lets you specify the ContextClass manually.
    *
    * @param array $record An array of database rows.
    * @return Model|null An array of instantiated ActiveRecord models from the provided data.
    */
    public function getByContext($contextClass, $contextID, $options = [])
    {
        if (!$this->fieldExists('ContextClass')) {
            throw new Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = Util::prepareOptions($options, [
            'conditions' => [],
            'order' => false,
        ]);

        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;

        $record = $this->getRecordByWhere($options['conditions'], $options);

        return $this->instantiateRecord($record);
    }

    /**
     * Get model object by configurable static::$handleField value
     *
     * @param int $id
     * @return Model|null
     */
    public function getByHandle($handle)
    {
        $handleField = $this->getHandleFieldName();

        if ($this->fieldExists($handleField)) {
            if ($Record = $this->getByField($handleField, $handle)) {
                return $Record;
            }
        }

        if (!is_int($handle) && !(is_string($handle) && ctype_digit($handle))) {
            return null;
        }

        return $this->getByID($handle);
    }

    /**
     * Get model object by primary key.
     *
     * @param int $id
     * @return Model|null
     */
    public function getByID($id)
    {
        $record = $this->getRecordByField($this->getPrimaryKeyName(), $id, true);
        return $this->instantiateRecord($record);
    }

    /**
     * Get model object by field.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param boolean $cacheIndex Optional. If we should cache the result or not. Default is false.
     * @return Model|null
     */
    public function getByField($field, $value, $cacheIndex = false)
    {
        $record = $this->getRecordByField($field, $value, $cacheIndex);

        return $this->instantiateRecord($record);
    }

    /**
     * Get record by field.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param boolean $cacheIndex Optional. If we should cache the result or not. Default is false.
     * @return array<Model>|null First database result.
     */
    public function getRecordByField($field, $value, $cacheIndex = false)
    {
        return $this->getRecordByWhere([$this->getColumnName($field) => $this->storage->escape($value)], $cacheIndex);
    }

    /**
     * Get the first result instantiated as a model from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options Only takes 'order' option. A raw database string that will be inserted into the OR clause of the query or an array of field/direction pairs.
     * @return Model|null Single model instantiated from the first database result
     */
    public function getByWhere($conditions, $options = [])
    {
        $record = $this->getRecordByWhere($conditions, $options);

        return $this->instantiateRecord($record);
    }

    /**
     * Get the first result as an array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options Only takes 'order' option. A raw database string that will be inserted into the OR clause of the query or an array of field/direction pairs.
     * @return array<Model>|null First database result.
     */
    public function getRecordByWhere($conditions, $options = [])
    {
        if (!is_array($conditions)) {
            $conditions = [$conditions];
        }

        $options = Util::prepareOptions($options, [
            'order' => false,
        ]);

        // initialize conditions and order
        $conditions = $this->mapConditions($conditions);
        $order = $options['order'] ? $this->mapFieldOrder($options['order']) : [];

        return $this->storage->oneRecord(
            (new Select())->setTable($this->getTableName())->where(join(') AND (', $conditions))->order($order ? join(',', $order) : '')->limit('1'),
            null,
            $this->getHandleExceptionCallback()
        );
    }

    /**
     * Get the first result instantiated as a model from a simple select query you can provide.
     *
     * @param string $query Database query. The passed in string will be passed through vsprintf or sprintf with $params.
     * @param array|string $params If an array will be passed through vsprintf as the second parameter with the query as the first. If a string will be used with sprintf instead. If nothing provided you must provide your own query.
     * @return Model|null Single model instantiated from the first database result
     */
    public function getByQuery($query, $params = [])
    {
        return $this->instantiateRecord($this->storage->oneRecord($query, $params, $this->getHandleExceptionCallback()));
    }

    /**
     * Get all models in the database by class name. This is a subclass utility method. Requires a Class field on the model.
     *
     * @param boolean $className The full name of the class including namespace. Optional. Will use the name of the current class if none provided.
     * @param array $options
     * @return array<Model>|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public function getAllByClass($className = false, $options = [])
    {
        return $this->getAllByField('Class', $className ? $className : $this->getModelClass(), $options);
    }

    /**
     * Get all models in the database by passing in an ActiveRecord model which has a 'ContextClass' field by the passed in records primary key.
     *
     * @param ActiveRecord $Record
     * @param array $options
     * @return array<Model>|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public function getAllByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->getAllByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }

    /**
     * @param string $contextClass
     * @param mixed $contextID
     * @param array $options
     * @return array<Model>|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public function getAllByContext($contextClass, $contextID, $options = [])
    {
        if (!$this->fieldExists('ContextClass')) {
            throw new Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = Util::prepareOptions($options, [
            'conditions' => [],
        ]);

        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;

        return $this->instantiateRecords($this->getAllRecordsByWhere($options['conditions'], $options));
    }

    /**
     * Get model objects by field and value.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param array $options
     * @return array<Model>|null Array of models instantiated from the database result.
     */
    public function getAllByField($field, $value, $options = [])
    {
        return $this->getAllByWhere([$field => $value], $options);
    }

    /**
     * Gets instantiated models as an array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options
     * @return array<Model>|null Array of models instantiated from the database result.
     */
    public function getAllByWhere($conditions = [], $options = [])
    {
        return $this->instantiateRecords($this->getAllRecordsByWhere($conditions, $options));
    }

    /**
     * Attempts to get all database records for this class and return them as an array of instantiated models.
     *
     * @param array $options
     * @return array<Model>|null
     */
    public function getAll($options = [])
    {
        return $this->instantiateRecords($this->getAllRecords($options));
    }

    /**
     * Attempts to get all database records for this class and returns them as is from the database.
     *
     * @param array $options
     * @return array<Model>|null
     */
    public function getAllRecords($options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false,
            'order' => false,
            'limit' => false,
            'calcFoundRows' => false,
            'offset' => 0,
        ]);

        $select = (new Select())->setTable($this->getTableName())->calcFoundRows();

        if ($options['order']) {
            $select->order(join(',', $this->mapFieldOrder($options['order'])));
        }

        if ($options['limit']) {
            $select->limit(sprintf('%u,%u', $options['offset'], $options['limit']));
        }
        if ($options['indexField']) {
            return $this->storage->table($this->getColumnName($options['indexField']), $select, null, null, $this->getHandleExceptionCallback());
        } else {
            return $this->storage->allRecords($select, null, $this->getHandleExceptionCallback());
        }
    }

    /**
     * Gets all records by a query you provide and then instantiates the results as an array of models.
     *
     * @param string $query Database query. The passed in string will be passed through vsprintf or sprintf with $params.
     * @param array|string $params If an array will be passed through vsprintf as the second parameter with the query as the first. If a string will be used with sprintf instead. If nothing provided you must provide your own query.
     * @return array<Model>|null Array of models instantiated from the first database result
     */
    public function getAllByQuery($query, $params = [])
    {
        return $this->instantiateRecords($this->storage->allRecords($query, $params, $this->getHandleExceptionCallback()));
    }

    /**
     * Loops over the data returned from the raw query and writes a new array where the key uses the $keyField parameter instead.
     *
     * @param string $keyField
     * @param string $query
     * @param array $params
     * @return array<Model>|null
     */
    public function getTableByQuery($keyField, $query, $params = [])
    {
        return $this->instantiateRecords($this->storage->table($keyField, $query, $params, $this->getHandleExceptionCallback()));
    }

    /**
     * Gets database results as array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options
     * @return array<Model>|null  Array of records from the database result.
     */
    public function getAllRecordsByWhere($conditions = [], $options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false,
            'order' => false,
            'limit' => false,
            'offset' => 0,
            'calcFoundRows' => !empty($options['limit']),
            'extraColumns' => false,
            'having' => false,
        ]);

        // initialize conditions
        if ($conditions) {
            if (is_string($conditions)) {
                $conditions = [$conditions];
            }

            $conditions = $this->mapConditions($conditions);
        }

        $tableAlias = $this->getSelectTableAlias();
        $select = (new Select())->setTable($this->getTableName())->setTableAlias($tableAlias);
        if ($options['calcFoundRows']) {
            $select->calcFoundRows();
        }

        $expression = sprintf('`%s`.*', $tableAlias);
        $select->expression($expression.$this->buildExtraColumns($options['extraColumns']));

        $whereClause = $conditions ? join(') AND (', $conditions) : null;

        if ($conditions) {
            $select->where($whereClause);
        }

        if ($options['having']) {
            $havingClause = $this->buildHaving($options['having'], $options['extraColumns']);

            if (Connections::getConnectionType() === \Divergence\IO\Database\PostgreSQL::class) {
                $select->where($whereClause ? $whereClause . ' AND ' . trim($havingClause) : trim($havingClause));
            } else {
                $select->having($havingClause);
            }
        }

        if ($options['order']) {
            $select->order(join(',', $this->mapFieldOrder($options['order'])));
        }

        if ($options['limit']) {
            $select->limit(sprintf('%u,%u', $options['offset'], $options['limit']));
        }

        if ($options['indexField']) {
            return $this->storage->table($this->getColumnName($options['indexField']), $select, null, null, $this->getHandleExceptionCallback());
        } else {
            return $this->storage->allRecords($select, null, $this->getHandleExceptionCallback());
        }
    }

    /**
     * Generates a unique string based on the provided text making sure that nothing it returns already exists in the database for the given handleField option. If none is provided the static config $handleField will be used.
     *
     * @param string $text
     * @param array $options
     * @return string A unique handle.
     */
    public function getUniqueHandle($text, $options = [])
    {
        // apply default options
        $options = Util::prepareOptions($options, [
            'handleField' => $this->getHandleFieldName(),
            'domainConstraints' => [],
            'alwaysSuffix' => false,
            'format' => '%s:%u',
        ]);

        // transliterate accented characters
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);

        // strip bad characters
        $handle = $strippedText = preg_replace(
            ['/\s+/', '/_*[^a-zA-Z0-9\-_:]+_*/', '/:[-_]/', '/^[-_]+/', '/[-_]+$/'],
            ['_', '-', ':', '', ''],
            trim($text)
        );

        $handle = trim($handle, '-_');

        $incarnation = 0;
        do {
            // TODO: check for repeat posting here?
            $incarnation++;

            if ($options['alwaysSuffix'] || $incarnation > 1) {
                $handle = sprintf($options['format'], $strippedText, $incarnation);
            }
        } while ($this->getByWhere(array_merge($options['domainConstraints'], [$options['handleField']=>$handle])));

        return $handle;
    }

    // TODO: make the handleField
    public function generateRandomHandle($length = 32)
    {
        do {
            $handle = substr(md5(mt_rand(0, mt_getrandmax())), 0, $length);
        } while ($this->getByField($this->getHandleFieldName(), $handle));

        return $handle;
    }

    /**
     * Builds the extra columns you might want to add to a database select query after the initial list of model fields.
     *
     * @param array|string $columns An array of keys and values or a string which will be added to a list of fields after the query's SELECT clause.
     * @return string|null Extra columns to add after a SELECT clause in a query. Always starts with a comma.
     */
    public function buildExtraColumns($columns)
    {
        if (!empty($columns)) {
            if (is_array($columns)) {
                foreach ($columns as $key => $value) {
                    return ', '.$value.' AS '.$key;
                }
            } else {
                return ', ' . $columns;
            }
        }
    }

    /**
     * Builds the HAVING clause of a MySQL database query.
     *
     * @param array|string $having Same as conditions. Can provide a string to use or an array of field/value pairs which will be joined by the AND operator.
     * @return string|null
     */
    public function buildHaving($having, $extraColumns = null)
    {
        if (!empty($having)) {
            $having = $this->replaceExtraColumnAliasesInHaving($having, $extraColumns);

            return ' (' . (is_array($having) ? join(') AND (', $this->mapConditions($having)) : $having) . ')';
        }
    }

    protected function replaceExtraColumnAliasesInHaving($having, $extraColumns)
    {
        if (Connections::getConnectionType() !== \Divergence\IO\Database\PostgreSQL::class || empty($extraColumns)) {
            return $having;
        }

        $aliases = $this->extractExtraColumnAliases($extraColumns);

        if (empty($aliases)) {
            return $having;
        }

        $replaceAliases = function ($clause) use ($aliases) {
            foreach ($aliases as $alias => $expression) {
                $clause = str_replace(
                    ['`' . $alias . '`', '"' . $alias . '"', $alias],
                    ['(' . $expression . ')', '(' . $expression . ')', '(' . $expression . ')'],
                    $clause
                );
            }

            return $clause;
        };

        if (is_array($having)) {
            foreach ($having as $key => $clause) {
                if (is_string($clause)) {
                    $having[$key] = $replaceAliases($clause);
                }
            }

            return $having;
        }

        return is_string($having) ? $replaceAliases($having) : $having;
    }

    protected function extractExtraColumnAliases($columns): array
    {
        $aliases = [];
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $key => $value) {
            $column = is_string($key) ? $value . ' AS ' . $key : $value;

            if (!is_string($column)) {
                continue;
            }

            if (preg_match('/^(.*?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', trim($column), $matches)) {
                $aliases[$matches[2]] = $matches[1];
            }
        }

        return $aliases;
    }

    protected function getSelectTableAlias(): string
    {
        return 'Record';
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
