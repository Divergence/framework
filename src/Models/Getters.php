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
use Divergence\Models\ActiveRecord;
use Divergence\IO\Database\MySQL as DB;

/**
 * @property string $handleField Defined in the model
 * @property string $primaryKey Defined in the model
 * @property string $tableName Defined in the model
 */
trait Getters
{
    /**
     * Converts database record array to a model. Will attempt to use the record's Class field value to as the class to instantiate as or the name of this class if none is provided.
     *
     * @param array $record Database row as an array.
     * @return static|null An instantiated ActiveRecord model from the provided data.
     */
    public static function instantiateRecord($record)
    {
        $className = static::_getRecordClass($record);
        return $record ? new $className($record) : null;
    }

    /**
     * Converts an array of database records to a model corresponding to each record. Will attempt to use the record's Class field value to as the class to instantiate as or the name of this class if none is provided.
     *
     * @param array $record An array of database rows.
     * @return static|null An array of instantiated ActiveRecord models from the provided data.
     */
    public static function instantiateRecords($records)
    {
        foreach ($records as &$record) {
            $className = static::_getRecordClass($record);
            $record = new $className($record);
        }

        return $records;
    }

    public static function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return static::getByContext($Record::$rootClass, $Record->getPrimaryKeyValue(), $options);
    }

    public static function getByContext($contextClass, $contextID, $options = [])
    {
        if (!static::fieldExists('ContextClass')) {
            throw new Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = Util::prepareOptions($options, [
            'conditions' => [],
            'order' => false,
        ]);

        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;

        $record = static::getRecordByWhere($options['conditions'], $options);

        $className = static::_getRecordClass($record);

        return $record ? new $className($record) : null;
    }

    public static function getByHandle($handle)
    {
        if (static::fieldExists(static::$handleField)) {
            if ($Record = static::getByField(static::$handleField, $handle)) {
                return $Record;
            }
        }
        return static::getByID($handle);
    }

    /**
     * Get model object by primary key.
     *
     * @param int $id
     * @return static|null
     */
    public static function getByID($id)
    {
        $record = static::getRecordByField(static::$primaryKey ? static::$primaryKey : 'ID', $id, true);

        return static::instantiateRecord($record);
    }

    /**
     * Get model object by field.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param boolean $cacheIndex Optional. If we should cache the result or not. Default is false.
     * @return static|null
     */
    public static function getByField($field, $value, $cacheIndex = false)
    {
        $record = static::getRecordByField($field, $value, $cacheIndex);

        return static::instantiateRecord($record);
    }

    /**
     * Get record by field.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param boolean $cacheIndex Optional. If we should cache the result or not. Default is false.
     * @return array|null First database result.
     */
    public static function getRecordByField($field, $value, $cacheIndex = false)
    {
        $query = 'SELECT * FROM `%s` WHERE `%s` = "%s" LIMIT 1';
        $params = [
            static::$tableName,
            static::_cn($field),
            DB::escape($value),
        ];

        if ($cacheIndex) {
            $key = sprintf('%s/%s:%s', static::$tableName, $field, $value);
            return DB::oneRecordCached($key, $query, $params, [static::class,'handleError']);
        } else {
            return DB::oneRecord($query, $params, [static::class,'handleError']);
        }
    }

    /**
     * Get the first result instantiated as a model from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options Only takes 'order' option. A raw database string that will be inserted into the OR clause of the query or an array of field/direction pairs.
     * @return static|null Single model instantiated from the first database result
     */
    public static function getByWhere($conditions, $options = [])
    {
        $record = static::getRecordByWhere($conditions, $options);

        return static::instantiateRecord($record);
    }

    /**
     * Get the first result as an array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options Only takes 'order' option. A raw database string that will be inserted into the OR clause of the query or an array of field/direction pairs.
     * @return array|null First database result.
     */
    public static function getRecordByWhere($conditions, $options = [])
    {
        if (!is_array($conditions)) {
            $conditions = [$conditions];
        }

        $options = Util::prepareOptions($options, [
            'order' => false,
        ]);

        // initialize conditions and order
        $conditions = static::_mapConditions($conditions);
        $order = $options['order'] ? static::_mapFieldOrder($options['order']) : [];

        return DB::oneRecord(
            'SELECT * FROM `%s` WHERE (%s) %s LIMIT 1',
            [
                static::$tableName,
                join(') AND (', $conditions),
                $order ? 'ORDER BY '.join(',', $order) : '',
            ],
            [static::class,'handleError']
        );
    }

    /**
     * Get the first result instantiated as a model from a simple select query you can provide.
     *
     * @param string $query Database query. The passed in string will be passed through vsprintf or sprintf with $params.
     * @param array|string $params If an array will be passed through vsprintf as the second parameter with the query as the first. If a string will be used with sprintf instead. If nothing provided you must provide your own query.
     * @return static|null Single model instantiated from the first database result
     */
    public static function getByQuery($query, $params = [])
    {
        return static::instantiateRecord(DB::oneRecord($query, $params, [static::class,'handleError']));
    }

    /**
     * Get all models in the database by class name. This is a subclass utility method. Requires a Class field on the model.
     *
     * @param boolean $className The full name of the class including namespace. Optional. Will use the name of the current class if none provided.
     * @param array $options
     * @return static[]|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public static function getAllByClass($className = false, $options = [])
    {
        return static::getAllByField('Class', $className ? $className : get_called_class(), $options);
    }

    /**
     * Get all models in the database by passing in an ActiveRecord model which has a 'ContextClass' field by the passed in records primary key.
     *
     * @param ActiveRecord $Record
     * @param array $options
     * @return static[]|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public static function getAllByContextObject(ActiveRecord $Record, $options = [])
    {
        return static::getAllByContext($Record::$rootClass, $Record->getPrimaryKeyValue(), $options);
    }


    public static function getAllByContext($contextClass, $contextID, $options = [])
    {
        if (!static::fieldExists('ContextClass')) {
            throw new Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = Util::prepareOptions($options, [
            'conditions' => [],
        ]);

        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;

        return static::instantiateRecords(static::getAllRecordsByWhere($options['conditions'], $options));
    }

    /**
     * Get model objects by field and value.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param array $options
     * @return static[]|null Array of models instantiated from the database result.
     */
    public static function getAllByField($field, $value, $options = [])
    {
        return static::getAllByWhere([$field => $value], $options);
    }

    /**
     * Gets instantiated models as an array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options
     * @return static[]|null Array of models instantiated from the database result.
     */
    public static function getAllByWhere($conditions = [], $options = [])
    {
        return static::instantiateRecords(static::getAllRecordsByWhere($conditions, $options));
    }

    /**
     * Attempts to get all database records for this class and return them as an array of instantiated models.
     *
     * @param array $options
     * @return static[]|null
     */
    public static function getAll($options = [])
    {
        return static::instantiateRecords(static::getAllRecords($options));
    }

    /**
     * Attempts to get all database records for this class and returns them as is from the database.
     *
     * @param array $options
     * @return array[]|null
     */
    public static function getAllRecords($options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false,
            'order' => false,
            'limit' => false,
            'calcFoundRows' => false,
            'offset' => 0,
        ]);

        $query = 'SELECT '.($options['calcFoundRows'] ? 'SQL_CALC_FOUND_ROWS' : '').'* FROM `%s`';
        $params = [
            static::$tableName,
        ];

        if ($options['order']) {
            $query .= ' ORDER BY ' . join(',', static::_mapFieldOrder($options['order']));
        }

        if ($options['limit']) {
            $query .= sprintf(' LIMIT %u,%u', $options['offset'], $options['limit']);
        }

        if ($options['indexField']) {
            return DB::table(static::_cn($options['indexField']), $query, $params, null, [static::class,'handleError']);
        } else {
            return DB::allRecords($query, $params, [static::class,'handleError']);
        }
    }

    /**
     * Gets all records by a query you provide and then instantiates the results as an array of models.
     *
     * @param string $query Database query. The passed in string will be passed through vsprintf or sprintf with $params.
     * @param array|string $params If an array will be passed through vsprintf as the second parameter with the query as the first. If a string will be used with sprintf instead. If nothing provided you must provide your own query.
     * @return static[]|null Array of models instantiated from the first database result
     */
    public static function getAllByQuery($query, $params = [])
    {
        return static::instantiateRecords(DB::allRecords($query, $params, [static::class,'handleError']));
    }

    public static function getTableByQuery($keyField, $query, $params = [])
    {
        return static::instantiateRecords(DB::table($keyField, $query, $params, [static::class,'handleError']));
    }

    /**
     * Gets database results as array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options
     * @return array[]|null Array of records from the database result.
     */
    public static function getAllRecordsByWhere($conditions = [], $options = [])
    {
        $className = get_called_class();

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

            $conditions = static::_mapConditions($conditions);
        }

        // build query
        $query  = 'SELECT %1$s `%3$s`.*';
        $query .= static::buildExtraColumns($options['extraColumns']);
        $query .= ' FROM `%2$s` AS `%3$s`';
        $query .= ' WHERE (%4$s)';

        $query .= static::buildHaving($options['having']);

        $params = [
            $options['calcFoundRows'] ? 'SQL_CALC_FOUND_ROWS' : '',
            static::$tableName,
            $className::$rootClass,
            $conditions ? join(') AND (', $conditions) : '1',
        ];

        if ($options['order']) {
            $query .= ' ORDER BY ' . join(',', static::_mapFieldOrder($options['order']));
        }

        if ($options['limit']) {
            $query .= sprintf(' LIMIT %u,%u', $options['offset'], $options['limit']);
        }

        if ($options['indexField']) {
            return DB::table(static::_cn($options['indexField']), $query, $params, null, [static::class,'handleError']);
        } else {
            return DB::allRecords($query, $params, [static::class,'handleError']);
        }
    }

    /**
     * Generates a unique string based on the provided text making sure that nothing it returns already exists in the database for the given handleField option. If none is provided the static config $handleField will be used.
     *
     * @param string $text
     * @param array $options
     * @return string A unique handle.
     */
    public static function getUniqueHandle($text, $options = [])
    {
        // apply default options
        $options = Util::prepareOptions($options, [
            'handleField' => static::$handleField,
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
        } while (static::getByWhere(array_merge($options['domainConstraints'], [$options['handleField']=>$handle])));

        return $handle;
    }
}
