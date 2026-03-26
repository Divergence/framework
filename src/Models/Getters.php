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

use Divergence\Models\ActiveRecord;

/**
 * @property string $handleField Defined in the model
 * @property string $primaryKey Defined in the model
 * @property string $tableName Defined in the model
 */
trait Getters
{
    public static function Factory(?string $modelClass = null): Factory
    {
        return new Factory($modelClass ?: static::class);
    }

    /**
     * Converts database record array to a model. Will attempt to use the record's Class field value to as the class to instantiate as or the name of this class if none is provided.
     *
     * @param array $record Database row as an array.
     * @return static|null An instantiated ActiveRecord model from the provided data.
     */
    public static function instantiateRecord($record)
    {
        return static::Factory()->instantiateRecord($record);
    }

    /**
     * Converts an array of database records to a model corresponding to each record. Will attempt to use the record's Class field value to as the class to instantiate as or the name of this class if none is provided.
     *
     * @param array $record An array of database rows.
     * @return array<static>|null An array of instantiated ActiveRecord models from the provided data.
     */
    public static function instantiateRecords($records)
    {
        return static::Factory()->instantiateRecords($records);
    }

    /**
     * Uses ContextClass and ContextID to get an object.
     * Quick way to attach things to other objects in a one-to-one relationship
     *
     * @param array $record An array of database rows.
     * @return static|null An array of instantiated ActiveRecord models from the provided data.
     */
    public static function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return static::Factory()->getByContextObject($Record, $options);
    }

    /**
    * Same as getByContextObject but this method lets you specify the ContextClass manually.
    *
    * @param array $record An array of database rows.
    * @return static|null An array of instantiated ActiveRecord models from the provided data.
    */
    public static function getByContext($contextClass, $contextID, $options = [])
    {
        return static::Factory()->getByContext($contextClass, $contextID, $options);
    }

    /**
     * Get model object by configurable static::$handleField value
     *
     * @param int $id
     * @return static|null
     */
    public static function getByHandle($handle)
    {
        return static::Factory()->getByHandle($handle);
    }

    /**
     * Get model object by primary key.
     *
     * @param int $id
     * @return static|null
     */
    public static function getByID($id)
    {
        return static::Factory()->getByID($id);
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
        return static::Factory()->getByField($field, $value, $cacheIndex);
    }

    /**
     * Get record by field.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param boolean $cacheIndex Optional. If we should cache the result or not. Default is false.
     * @return array<static>|null First database result.
     */
    public static function getRecordByField($field, $value, $cacheIndex = false)
    {
        return static::Factory()->getRecordByField($field, $value, $cacheIndex);
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
        return static::Factory()->getByWhere($conditions, $options);
    }

    /**
     * Get the first result as an array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options Only takes 'order' option. A raw database string that will be inserted into the OR clause of the query or an array of field/direction pairs.
     * @return array<static>|null First database result.
     */
    public static function getRecordByWhere($conditions, $options = [])
    {
        return static::Factory()->getRecordByWhere($conditions, $options);
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
        return static::Factory()->getByQuery($query, $params);
    }

    /**
     * Get all models in the database by class name. This is a subclass utility method. Requires a Class field on the model.
     *
     * @param boolean $className The full name of the class including namespace. Optional. Will use the name of the current class if none provided.
     * @param array $options
     * @return array<static>|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public static function getAllByClass($className = false, $options = [])
    {
        return static::Factory()->getAllByClass($className, $options);
    }

    /**
     * Get all models in the database by passing in an ActiveRecord model which has a 'ContextClass' field by the passed in records primary key.
     *
     * @param ActiveRecord $Record
     * @param array $options
     * @return array<static>|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public static function getAllByContextObject(ActiveRecord $Record, $options = [])
    {
        return static::Factory()->getAllByContextObject($Record, $options);
    }

    /**
     * @param string $contextClass
     * @param mixed $contextID
     * @param array $options
     * @return array<static>|null Array of instantiated ActiveRecord models returned from the database result.
     */
    public static function getAllByContext($contextClass, $contextID, $options = [])
    {
        return static::Factory()->getAllByContext($contextClass, $contextID, $options);
    }

    /**
     * Get model objects by field and value.
     *
     * @param string $field Field name
     * @param string $value Field value
     * @param array $options
     * @return array<static>|null Array of models instantiated from the database result.
     */
    public static function getAllByField($field, $value, $options = [])
    {
        return static::Factory()->getAllByField($field, $value, $options);
    }

    /**
     * Gets instantiated models as an array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options
     * @return array<static>|null Array of models instantiated from the database result.
     */
    public static function getAllByWhere($conditions = [], $options = [])
    {
        return static::Factory()->getAllByWhere($conditions, $options);
    }

    /**
     * Attempts to get all database records for this class and return them as an array of instantiated models.
     *
     * @param array $options
     * @return array<static>|null
     */
    public static function getAll($options = [])
    {
        return static::Factory()->getAll($options);
    }

    /**
     * Attempts to get all database records for this class and returns them as is from the database.
     *
     * @param array $options
     * @return array<static>|null
     */
    public static function getAllRecords($options = [])
    {
        return static::Factory()->getAllRecords($options);
    }

    /**
     * Gets all records by a query you provide and then instantiates the results as an array of models.
     *
     * @param string $query Database query. The passed in string will be passed through vsprintf or sprintf with $params.
     * @param array|string $params If an array will be passed through vsprintf as the second parameter with the query as the first. If a string will be used with sprintf instead. If nothing provided you must provide your own query.
     * @return array<static>|null Array of models instantiated from the first database result
     */
    public static function getAllByQuery($query, $params = [])
    {
        return static::Factory()->getAllByQuery($query, $params);
    }

    /**
     * Loops over the data returned from the raw query and writes a new array where the key uses the $keyField parameter instead.
     *
     * @param string $keyField
     * @param string $query
     * @param array $params
     * @return array<static>|null
     */
    public static function getTableByQuery($keyField, $query, $params = [])
    {
        return static::Factory()->getTableByQuery($keyField, $query, $params);
    }

    /**
     * Gets database results as array from a simple select query with a where clause you can provide.
     *
     * @param array|string $conditions If passed as a string a database Where clause. If an array of field/value pairs will convert to a series of `field`='value' conditions joined with an AND operator.
     * @param array|string $options
     * @return array<static>|null  Array of records from the database result.
     */
    public static function getAllRecordsByWhere($conditions = [], $options = [])
    {
        return static::Factory()->getAllRecordsByWhere($conditions, $options);
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
        return static::Factory()->getUniqueHandle($text, $options);
    }

    // TODO: make the handleField
    public static function generateRandomHandle($length = 32)
    {
        return static::Factory()->generateRandomHandle($length);
    }

    /**
     * Builds the extra columns you might want to add to a database select query after the initial list of model fields.
     *
     * @param array|string $columns An array of keys and values or a string which will be added to a list of fields after the query's SELECT clause.
     * @return string|null Extra columns to add after a SELECT clause in a query. Always starts with a comma.
     */
    public static function buildExtraColumns($columns)
    {
        return static::Factory()->buildExtraColumns($columns);
    }

    /**
     * Builds the HAVING clause of a MySQL database query.
     *
     * @param array|string $having Same as conditions. Can provide a string to use or an array of field/value pairs which will be joined by the AND operator.
     * @return string|null
     */
    public static function buildHaving($having)
    {
        return static::Factory()->buildHaving($having);
    }
}
