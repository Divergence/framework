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
use JsonSerializable;
use Divergence\IO\Database\SQL as SQL;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Models\Interfaces\FieldSetMapper;
use Divergence\Models\SetMappers\DefaultSetMapper;

/**
 * ActiveRecord
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 * @author  Chris Alfano <themightychris@gmail.com>
 *
 * @property-read bool $isDirty      False by default. Set to true only when an object has had any field change from it's state when it was instantiated.
 * @property-read bool $isPhantom    True if this object was instantiated as a brand new object and isn't yet saved.
 * @property-read bool $wasPhantom   True if this object was originally instantiated as a brand new object. Will stay true even if saved during that PHP runtime.
 * @property-read bool $isValid      True if this object is valid. This value is true by default and will only be set to false if the validator is executed first and finds a validation problem.
 * @property-read bool $isNew        False by default. Set to true only when an object that isPhantom is saved.
 * @property-read bool $isUpdated    False by default. Set to true when an object that already existed in the data store is saved.
 *
 * @property int        $ID Default primary key field. Part of Divergence\Models\Model but used in this file as a default.
 * @property string     $Class Name of this fully qualified PHP class for use with subclassing to explicitly specify which class to instantiate a record as when pulling from datastore. Part of Divergence\Models\Model but used in this file as a default.
 * @property mixed      $Created Timestamp of when this record was created. Supports Unix timestamp as well as any format accepted by PHP's strtotime as well as MySQL standard. Part of Divergence\Models\Model but used in this file as a default.
 * @property int        $CreatorID A standard user ID field for use by your login & authentication system. Part of Divergence\Models\Model but used in this file as a default.
 *
 * @property-read array $validationErrors    An empty string by default. Returns validation errors as an array.
 * @property-read array $data                A plain PHP array of the fields and values for this model object.
 * @property-read array $originalValues      A plain PHP array of the fields and values for this model object when it was instantiated.
 *
 */
class ActiveRecord implements JsonSerializable
{
    /**
     * @var bool $autoCreateTables Set this to true if you want the table(s) to automatically be created when not found.
     */
    public static $autoCreateTables = true;

    /**
     * @var string $tableName Name of table
     */
    public static $tableName = 'records';

    /**
     *
     * @var string $singularNoun Noun to describe singular object
     */
    public static $singularNoun = 'record';

    /**
     *
     * @var string $pluralNoun Noun to describe a plurality of objects
     */
    public static $pluralNoun = 'records';

    /**
     *
     * @var array $fieldDefaults Defaults values for field definitions
     */
    public static $fieldDefaults = [
        'type' => 'string',
        'notnull' => true,
    ];

    /**
     * @var array $fields Field definitions
     */
    public static $fields = [];

    /**
     * @var array $indexes Index definitions
     */
    public static $indexes = [];


    /**
    * @var array $validators Validation checks
    */
    public static $validators = [];

    /**
     * @var array $relationships Relationship definitions
     */
    public static $relationships = [];


    /**
     * Class names of possible contexts
     * @var array
     */
    public static $contextClasses;

    /**
     *  @var string|null $primaryKey The primary key for this model. Optional. Defaults to ID
     */
    public static $primaryKey = null;

    /**
     *  @var string $handleField Field which should be treated as unique but generated automatically.
     */
    public static $handleField = 'Handle';

    /**
     *  @var null|string $rootClass The root class.
     */
    public static $rootClass = null;

    /**
     *  @var null|string $defaultClass The default class to be used when creating a new object.
     */
    public static $defaultClass = null;

    /**
     *  @var array $subClasses Array of class names providing valid classes you can use for this model.
     */
    public static $subClasses = [];

    /**
     *  @var callable $beforeSave Runs inside the save() method before save actually happens.
     */
    public static $beforeSave;

    /**
     *  @var callable $afterSave Runs inside the save() method after save if no exception was thrown.
     */
    public static $afterSave;


    // versioning
    public static $historyTable;
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    /**
     * Internal registry of fields that comprise this class. The setting of this variable of every parent derived from a child model will get merged.
     *
     * @var array $_classFields
     */
    protected static $_classFields = [];

    /**
     * Internal registry of relationships that are part of this class. The setting of this variable of every parent derived from a child model will get merged.
     *
     * @var array $_classFields
     */
    protected static $_classRelationships = [];

    /**
     * Internal registry of before save PHP callables that are part of this class. The setting of this variable of every parent derived from a child model will get merged.
     *
     * @var array $_classBeforeSave
     */
    protected static $_classBeforeSave = [];

    /**
     * Internal registry of after save PHP callables that are part of this class. The setting of this variable of every parent derived from a child model will get merged.
     *
     * @var array $_classAfterSave
     */
    protected static $_classAfterSave = [];

    /**
     * Global registry of booleans that check if a given model has had it's fields defined in a static context. The key is a class name and the value is a simple true / false.
     *
     * @used-by ActiveRecord::init()
     *
     * @var array $_fieldsDefined
     */
    protected static $_fieldsDefined = [];

    /**
     * Global registry of booleans that check if a given model has had it's relationships defined in a static context. The key is a class name and the value is a simple true / false.
     *
     * @used-by ActiveRecord::init()
     *
     * @var array $_relationshipsDefined
     */
    protected static $_relationshipsDefined = [];
    protected static $_eventsDefined = [];

    /**
     * @var array $_record Raw array data for this model.
     */
    protected $_record;

    /**
     * @var array $_convertedValues Raw array data for this model of data normalized for it's field type.
     */
    protected $_convertedValues;

    /**
     * @var RecordValidator $_validator Instance of a RecordValidator object.
     */
    protected $_validator;

    /**
     * @var array $_validationErrors Array of validation errors if there are any.
     */
    protected $_validationErrors;

    /**
     * @var array $_originalValues If any values have been changed the initial value is stored here.
     */
    protected $_originalValues;


    /**
     * False by default. Set to true only when an object has had any field change from it's state when it was instantiated.
     *
     * @var bool $_isDirty
     *
     * @used-by $this->save()
     * @used-by $this->__get()
     */
    protected $_isDirty;

    /**
     * True if this object was instantiated as a brand new object and isn't yet saved.
     *
     * @var bool $_isPhantom
     *
     * @used-by $this->save()
     * @used-by $this->__get()
     */
    protected $_isPhantom;

    /**
     * True if this object was originally instantiated as a brand new object. Will stay true even if saved during that PHP runtime.
     *
     * @var bool $_wasPhantom
     *
     * @used-by $this->__get()
     */
    protected $_wasPhantom;

    /**
     * True if this object is valid. This value is true by default and will only be set to false if the validator is executed first and finds a validation problem.
     *
     * @var bool $_isValid
     *
     * @used-by $this->__get()
     */
    protected $_isValid;

    /**
     * False by default. Set to true only when an object that isPhantom is saved.
     *
     * @var bool $_isNew
     *
     * @used-by $this->save()
     * @used-by $this->__get()
     */
    protected $_isNew;

    /**
     * False by default. Set to true when an object that already existed in the data store is saved.
     *
     * @var bool $_isUpdated
     *
     * @used-by $this->__get()
     */
    protected $_isUpdated;

    /** Field Mapper */
    protected ?FieldSetMapper $fieldSetMapper;

    /**
     * __construct Instantiates a Model and returns.
     *
     * @param array $record Raw array data to start off the model.
     * @param boolean $isDirty Whether or not to treat this object as if it was modified from the start.
     * @param boolean $isPhantom Whether or not to treat this object as a brand new record not yet in the database.
     *
     * @uses static::init
     *
     * @return static Instance of the value of $this->Class
     */
    public function __construct($record = [], $isDirty = false, $isPhantom = null)
    {
        $this->_record = $record;
        $this->_isPhantom = isset($isPhantom) ? $isPhantom : empty($record);
        $this->_wasPhantom = $this->_isPhantom;
        $this->_isDirty = $this->_isPhantom || $isDirty;
        $this->_isNew = false;
        $this->_isUpdated = false;

        $this->_isValid = true;
        $this->_validationErrors = [];
        $this->_originalValues = [];

        static::init();

        // set Class
        if (static::fieldExists('Class') && !$this->Class) {
            $this->Class = get_class($this);
        }
    }

    /**
     * __get Passthru to getValue($name)
     *
     * @param string $name Name of the magic field you want.
     *
     * @return mixed The return of $this->getValue($name)
     */
    public function __get($name)
    {
        return $this->getValue($name);
    }

    /**
     * Passthru to setValue($name,$value)
     *
     * @param string $name Name of the magic field to set.
     * @param mixed $value Value to set.
     *
     * @return mixed The return of $this->setValue($name,$value)
     */
    public function __set($name, $value)
    {
        return $this->setValue($name, $value);
    }

    /**
     * Tests if a magic class attribute is set or not.
     *
     * @param string $name Name of the magic field to set.
     *
     * @return bool Returns true if a value was returned by $this->getValue($name), false otherwise.
     */
    public function __isset($name)
    {
        $value = $this->getValue($name);
        return isset($value);
    }

    /**
     * Gets the primary key field for his model.
     *
     * @return string ID by default or static::$primaryKey if it's set.
     */
    public function getPrimaryKey()
    {
        return isset(static::$primaryKey) ? static::$primaryKey : 'ID';
    }

    /**
     * Gets the primary key value for his model.
     *
     * @return mixed The primary key value for this object.
     */
    public function getPrimaryKeyValue()
    {
        if (isset(static::$primaryKey)) {
            return $this->__get(static::$primaryKey);
        } else {
            return $this->ID;
        }
    }

    /**
     * init Initializes the model by checking the ancestor tree for the existence of various config fields and merges them.
     *
     * @uses static::$_fieldsDefined Sets static::$_fieldsDefined[get_called_class()] to true after running.
     * @uses static::$_relationshipsDefined Sets static::$_relationshipsDefined[get_called_class()] to true after running.
     * @uses static::$_eventsDefined Sets static::$_eventsDefined[get_called_class()] to true after running.
     *
     * @used-by static::__construct()
     * @used-by static::fieldExists()
     * @used-by static::getClassFields()
     * @used-by static::getColumnName()
     *
     * @return void
     */
    public static function init()
    {
        $className = get_called_class();
        if (empty(static::$_fieldsDefined[$className])) {
            static::_defineFields();
            static::_initFields();

            static::$_fieldsDefined[$className] = true;
        }
        if (empty(static::$_relationshipsDefined[$className]) && static::isRelational()) {
            static::_defineRelationships();
            static::_initRelationships();

            static::$_relationshipsDefined[$className] = true;
        }

        if (empty(static::$_eventsDefined[$className])) {
            static::_defineEvents();

            static::$_eventsDefined[$className] = true;
        }
    }

    /**
     * getValue Pass thru for __get
     *
     * @param string $name The name of the field you want to get.
     *
     * @return mixed Value of the field you wanted if it exists or null otherwise.
     */
    public function getValue($name)
    {
        switch ($name) {
            case 'isDirty':
                return $this->_isDirty;

            case 'isPhantom':
                return $this->_isPhantom;

            case 'wasPhantom':
                return $this->_wasPhantom;

            case 'isValid':
                return $this->_isValid;

            case 'isNew':
                return $this->_isNew;

            case 'isUpdated':
                return $this->_isUpdated;

            case 'validationErrors':
                return array_filter($this->_validationErrors);

            case 'data':
                return $this->getData();

            case 'originalValues':
                return $this->_originalValues;

            default:
                {
                    // handle field
                    if (static::fieldExists($name)) {
                        return $this->_getFieldValue($name);
                    }
                    // handle relationship
                    elseif (static::isRelational()) {
                        if (static::_relationshipExists($name)) {
                            return $this->_getRelationshipValue($name);
                        }
                    }
                    // default Handle to ID if not caught by fieldExists
                    elseif ($name == static::$handleField) {
                        return $this->ID;
                    }
                }
        }
        // undefined
        return null;
    }

    /**
     * Sets a value on this model.
     *
     * @param string $name
     * @param mixed $value
     * @return void|false False if the field does not exist. Void otherwise.
     */
    public function setValue($name, $value)
    {
        // handle field
        if (static::fieldExists($name)) {
            $this->_setFieldValue($name, $value);
        }
        // undefined
        else {
            return false;
        }
    }

    /**
     * Checks if this model is versioned.
     *
     * @return boolean Returns true if this class is defined with Divergence\Models\Versioning as a trait.
     */
    public static function isVersioned()
    {
        return in_array('Divergence\\Models\\Versioning', class_uses(get_called_class()));
    }

    /**
     * Checks if this model is ready for relationships.
     *
     * @return boolean Returns true if this class is defined with Divergence\Models\Relations as a trait.
     */
    public static function isRelational()
    {
        return in_array('Divergence\\Models\\Relations', class_uses(get_called_class()));
    }

    /**
     * Create a new object from this model.
     *
     * @param array $values Array of keys as fields and values.
     * @param boolean $save If the object should be immediately saved to database before being returned.
     * @return static An object of this model.
     */
    public static function create($values = [], $save = false)
    {
        $className = get_called_class();

        // create class
        /** @var ActiveRecord */
        $ActiveRecord = new $className();
        $ActiveRecord->setFields($values);

        if ($save) {
            $ActiveRecord->save();
        }

        return $ActiveRecord;
    }

    /**
     * Checks if a model is a certain class.
     * @param string $class Check if the model matches this class.
     * @return boolean True if model matches the class provided. False otherwise.
     */
    public function isA($class)
    {
        return is_a($this, $class);
    }

    /**
     * Used to instantiate a new model of a different class with this model's field's. Useful when you have similar classes or subclasses with the same parent.
     *
     * @param string $className If you leave this blank the return will be $this
     * @param array $fieldValues Optional. Any field values you want to override.
     * @return static A new model of a different class with this model's field's. Useful when you have similar classes or subclasses with the same parent.
     */
    public function changeClass($className = false, $fieldValues = false)
    {
        if (!$className) {
            return $this;
        }

        $this->_record[static::_cn('Class')] = $className;
        $ActiveRecord = new $className($this->_record, true, $this->isPhantom);

        if ($fieldValues) {
            $ActiveRecord->setFields($fieldValues);
        }

        if (!$this->isPhantom) {
            $ActiveRecord->save();
        }

        return $ActiveRecord;
    }

    /**
     * Change multiple fields in the model with an array.
     *
     * @param array $values Field/values array to change multiple fields in this model.
     * @return void
     */
    public function setFields($values)
    {
        foreach ($values as $field => $value) {
            $this->_setFieldValue($field, $value);
        }
    }

    /**
     * Change one field in the model.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     */
    public function setField($field, $value)
    {
        $this->_setFieldValue($field, $value);
    }

    /**
     * Implements JsonSerializable for this class.
     *
     * @return array Return for extension JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->getData();
    }

    /**
     *  Gets normalized object data.
     *
     *  @return array The model's data as a normal array with any validation errors included.
     */
    public function getData()
    {
        $data = [];

        foreach (static::$_classFields[get_called_class()] as $field => $options) {
            $data[$field] = $this->_getFieldValue($field);
        }

        if ($this->validationErrors) {
            $data['validationErrors'] = $this->validationErrors;
        }

        return $data;
    }

    /**
     * Checks if a field has been changed from it's value when this object was created.
     *
     * @param string $field
     * @return boolean
     */
    public function isFieldDirty($field)
    {
        return $this->isPhantom || array_key_exists($field, $this->_originalValues);
    }

    /**
     * Gets values that this model was instantiated with for a given field.
     *
     * @param string $field Field name
     * @return mixed
     */
    public function getOriginalValue($field)
    {
        return $this->_originalValues[$field];
    }

    /**
     * Fires a DB::clearCachedRecord a key static::$tableName.'/'.static::getPrimaryKey()
     *
     * @return void
     */
    public function clearCaches()
    {
        foreach ($this->getClassFields() as $field => $options) {
            if (!empty($options['unique']) || !empty($options['primary'])) {
                $key = sprintf('%s/%s', static::$tableName, $field);
                DB::clearCachedRecord($key);
            }
        }
    }

    /**
     * Runs the before save event function one at a time for any class that had $beforeSave configured in the ancestor tree.
     */
    public function beforeSave()
    {
        foreach (static::$_classBeforeSave as $beforeSave) {
            if (is_callable($beforeSave)) {
                $beforeSave($this);
            }
        }
    }

    /**
     * Runs the after save event function one at a time for any class that had $beforeSave configured in the ancestor tree. Will only fire if save was successful.
     */
    public function afterSave()
    {
        foreach (static::$_classAfterSave as $afterSave) {
            if (is_callable($afterSave)) {
                $afterSave($this);
            }
        }
    }

    /**
     * Saves this object to the database currently in use.
     *
     * @param bool $deep Default is true. When true will try to save any dirty models in any defined and initialized relationships.
     *
     * @uses $this->_isPhantom
     * @uses $this->_isDirty
     */
    public function save($deep = true)
    {
        // run before save
        $this->beforeSave();

        if (static::isVersioned()) {
            $this->beforeVersionedSave();
        }

        // set created
        if (static::fieldExists('Created') && (!$this->Created || ($this->Created == 'CURRENT_TIMESTAMP'))) {
            $this->Created = time();
        }

        // validate
        if (!$this->validate($deep)) {
            throw new Exception('Cannot save invalid record');
        }

        $this->clearCaches();

        if ($this->isDirty) {
            // prepare record values
            $recordValues = $this->_prepareRecordValues();

            // transform record to set array
            $set = static::_mapValuesToSet($recordValues);
            // create new or update existing
            if ($this->_isPhantom) {
                DB::nonQuery(
                    'INSERT INTO `%s` SET %s',
                    [
                        static::$tableName,
                        join(',', $set),
                    ],
                    [static::class,'handleError']
                );
                $this->_record[static::$primaryKey ? static::$primaryKey : 'ID'] = DB::insertID();
                $this->_isPhantom = false;
                $this->_isNew = true;
            } elseif (count($set)) {
                DB::nonQuery(
                    'UPDATE `%s` SET %s WHERE `%s` = %u',
                    [
                        static::$tableName,
                        join(',', $set),
                        static::_cn(static::$primaryKey ? static::$primaryKey : 'ID'),
                        $this->getPrimaryKeyValue(),
                    ],
                    [static::class,'handleError']
                );

                $this->_isUpdated = true;
            }

            // update state
            $this->_isDirty = false;
            if (static::isVersioned()) {
                $this->afterVersionedSave();
            }
        }
        $this->afterSave();
    }


    /**
     * Deletes this object.
     *
     * @return bool True if database returns number of affected rows above 0. False otherwise.
     */
    public function destroy()
    {
        if (static::isVersioned()) {
            if (static::$createRevisionOnDestroy) {
                // save a copy to history table
                if ($this->fieldExists('Created')) {
                    $this->Created = time();
                }

                $recordValues = $this->_prepareRecordValues();
                $set = static::_mapValuesToSet($recordValues);

                DB::nonQuery(
                    'INSERT INTO `%s` SET %s',
                    [
                                static::getHistoryTable(),
                                join(',', $set),
                        ]
                );
            }
        }

        return static::delete($this->getPrimaryKeyValue());
    }

    /**
     * Delete by ID
     *
     * @param int $id
     * @return bool True if database returns number of affected rows above 0. False otherwise.
     */
    public static function delete($id)
    {
        DB::nonQuery('DELETE FROM `%s` WHERE `%s` = %u', [
            static::$tableName,
            static::_cn(static::$primaryKey ? static::$primaryKey : 'ID'),
            $id,
        ], [static::class,'handleError']);

        return DB::affectedRows() > 0;
    }

    /**
     * Builds the extra columns you might want to add to a database select query after the initial list of model fields.
     *
     * @param array|string $columns An array of keys and values or a string which will be added to a list of fields after the query's SELECT clause.
     * @return string|null Extra columns to add after a SELECT clause in a query. Always starts with a comma.
     */
    public static function buildExtraColumns($columns)
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
    public static function buildHaving($having)
    {
        if (!empty($having)) {
            return ' HAVING (' . (is_array($having) ? join(') AND (', static::_mapConditions($having)) : $having) . ')';
        }
    }


    // TODO: make the handleField
    public static function generateRandomHandle($length = 32)
    {
        do {
            $handle = substr(md5(mt_rand(0, mt_getrandmax())), 0, $length);
        } while (static::getByField(static::$handleField, $handle));

        return $handle;
    }

    /**
     * Checks of a field exists for this model in the fields config.
     *
     * @param string $field Name of the field
     * @return bool True if the field exists. False otherwise.
     */
    public static function fieldExists($field)
    {
        static::init();
        return array_key_exists($field, static::$_classFields[get_called_class()]);
    }

    /**
     * Returns the current configuration of class fields for the called class.
     *
     * @return array Current configuration of class fields for the called class.
     */
    public static function getClassFields()
    {
        static::init();
        return static::$_classFields[get_called_class()];
    }

    /**
     * Returns either a field option or an array of all the field options.
     *
     * @param string $field Name of the field.
     * @param boolean $optionKey
     * @return void
     */
    public static function getFieldOptions($field, $optionKey = false)
    {
        if ($optionKey) {
            return static::$_classFields[get_called_class()][$field][$optionKey];
        } else {
            return static::$_classFields[get_called_class()][$field];
        }
    }

    /**
     * Returns columnName for given field
     * @param string $field name of field
     * @return string column name
     */
    public static function getColumnName($field)
    {
        static::init();
        if (!static::fieldExists($field)) {
            throw new Exception('getColumnName called on nonexisting column: ' . get_called_class().'->'.$field);
        }

        return static::$_classFields[get_called_class()][$field]['columnName'];
    }

    public static function mapFieldOrder($order)
    {
        return static::_mapFieldOrder($order);
    }

    public static function mapConditions($conditions)
    {
        return static::_mapConditions($conditions);
    }

    /**
     * Returns static::$rootClass for the called class.
     *
     * @return string static::$rootClass for the called class.
     */
    public function getRootClass()
    {
        return static::$rootClass;
    }

    /**
     * Sets an array of validation errors for this object.
     *
     * @param array $array Validation errors in the form Field Name => error message
     * @return void
     */
    public function addValidationErrors($array)
    {
        foreach ($array as $field => $errorMessage) {
            $this->addValidationError($field, $errorMessage);
        }
    }

    /**
     * Sets a validation error for this object. Sets $this->_isValid to false.
     *
     * @param string $field
     * @param string $errorMessage
     * @return void
     */
    public function addValidationError($field, $errorMessage)
    {
        $this->_isValid = false;
        $this->_validationErrors[$field] = $errorMessage;
    }

    /**
     * Get a validation error for a given field.
     *
     * @param string $field Name of the field.
     * @return string|null A validation error for the field. Null is no validation error found.
     */
    public function getValidationError($field)
    {
        // break apart path
        $crumbs = explode('.', $field);

        // resolve path recursively
        $cur = &$this->_validationErrors;
        while ($crumb = array_shift($crumbs)) {
            if (array_key_exists($crumb, $cur)) {
                $cur = &$cur[$crumb];
            } else {
                return null;
            }
        }

        // return current value
        return $cur;
    }

    /**
     * Validates the model. Instantiates a new RecordValidator object and sets it to $this->_validator.
     * Then validates against the set validators in this model. Returns $this->_isValid
     *
     * @param boolean $deep If true will attempt to validate any already loaded relationship members.
     * @return bool $this->_isValid which could be set to true or false depending on what happens with the RecordValidator.
     */
    public function validate($deep = true)
    {
        $this->_isValid = true;
        $this->_validationErrors = [];

        if (!isset($this->_validator)) {
            $this->_validator = new RecordValidator($this->_record);
        } else {
            $this->_validator->resetErrors();
        }

        foreach (static::$validators as $validator) {
            $this->_validator->validate($validator);
        }

        $this->finishValidation();

        if ($deep) {
            // validate relationship objects
            if (!empty(static::$_classRelationships[get_called_class()])) {
                foreach (static::$_classRelationships[get_called_class()] as $relationship => $options) {
                    if (empty($this->_relatedObjects[$relationship])) {
                        continue;
                    }


                    if ($options['type'] == 'one-one') {
                        if ($this->_relatedObjects[$relationship]->isDirty) {
                            $this->_relatedObjects[$relationship]->validate();
                            $this->_isValid = $this->_isValid && $this->_relatedObjects[$relationship]->isValid;
                            $this->_validationErrors[$relationship] = $this->_relatedObjects[$relationship]->validationErrors;
                        }
                    } elseif ($options['type'] == 'one-many') {
                        foreach ($this->_relatedObjects[$relationship] as $i => $object) {
                            if ($object->isDirty) {
                                $object->validate();
                                $this->_isValid = $this->_isValid && $object->isValid;
                                $this->_validationErrors[$relationship][$i] = $object->validationErrors;
                            }
                        }
                    }
                } // foreach
            } // if
        } // if ($deep)

        return $this->_isValid;
    }

    /**
     * Handle any errors that come from the database client in the process of running a query.
     * If the error code from MySQL 42S02 (table not found) is thrown this method will attempt to create the table before running the original query and returning.
     * Other errors will be routed through to DB::handleError
     *
     * @param string $query
     * @param array $queryLog
     * @param array|string $parameters
     * @return mixed Retried query result or the return from DB::handleError
     */
    public static function handleError($query = null, $queryLog = null, $parameters = null)
    {
        $Connection = DB::getConnection();

        if ($Connection->errorCode() == '42S02' && static::$autoCreateTables) {
            $CreateTable = SQL::getCreateTable(static::$rootClass);

            // history versions table
            if (static::isVersioned()) {
                $CreateTable .= SQL::getCreateTable(static::$rootClass, true);
            }

            $Statement = $Connection->query($CreateTable);

            // check for errors
            $ErrorInfo = $Statement->errorInfo();

            // handle query error
            if ($ErrorInfo[0] != '00000') {
                self::handleError($query, $queryLog);
            }

            // clear buffer (required for the next query to work without running fetchAll first
            $Statement->closeCursor();

            return $Connection->query($query); // now the query should finish with no error
        } else {
            return DB::handleError($query, $queryLog);
        }
    }

    /**
     * Iterates through all static::$beforeSave and static::$afterSave in this class and any of it's parent classes.
     * Checks if they are callables and if they are adds them to static::$_classBeforeSave[] and static::$_classAfterSave[]
     *
     * @return void
     *
     * @uses static::$beforeSave
     * @uses static::$afterSave
     * @uses static::$_classBeforeSave
     * @uses static::$_classAfterSave
     */
    protected static function _defineEvents()
    {
        // run before save
        $className = get_called_class();

        // merge fields from first ancestor up
        $classes = class_parents($className);
        array_unshift($classes, $className);

        while ($class = array_pop($classes)) {
            if (is_callable($class::$beforeSave)) {
                if (!empty($class::$beforeSave)) {
                    if (!in_array($class::$beforeSave, static::$_classBeforeSave)) {
                        static::$_classBeforeSave[] = $class::$beforeSave;
                    }
                }
            }

            if (is_callable($class::$afterSave)) {
                if (!empty($class::$afterSave)) {
                    if (!in_array($class::$afterSave, static::$_classAfterSave)) {
                        static::$_classAfterSave[] = $class::$afterSave;
                    }
                }
            }
        }
    }

    /**
     * Merges all static::$_classFields in this class and any of it's parent classes.
     * Sets the merged value to static::$_classFields[get_called_class()]
     *
     * @return void
     *
     * @uses static::$_classFields
     * @uses static::$_classFields
     */
    protected static function _defineFields()
    {
        $className = get_called_class();

        // skip if fields already defined
        if (isset(static::$_classFields[$className])) {
            return;
        }

        // merge fields from first ancestor up
        $classes = class_parents($className);
        array_unshift($classes, $className);

        static::$_classFields[$className] = [];
        while ($class = array_pop($classes)) {
            if (!empty($class::$fields)) {
                static::$_classFields[$className] = array_merge(static::$_classFields[$className], $class::$fields);
            }
        }

        // versioning
        if (static::isVersioned()) {
            static::$_classFields[$className] = array_merge(static::$_classFields[$className], static::$versioningFields);
        }
    }


    /**
     * Called after _defineFields to initialize and apply defaults to the fields property
     * Must be idempotent as it may be applied multiple times up the inheritance chain
     * @return void
     *
     * @uses static::$_classFields
     */
    protected static function _initFields()
    {
        $className = get_called_class();
        $optionsMask = [
            'type' => null,
            'length' => null,
            'primary' => null,
            'unique' => null,
            'autoincrement' => null,
            'notnull' => null,
            'unsigned' => null,
            'default' => null,
            'values' => null,
        ];

        // apply default values to field definitions
        if (!empty(static::$_classFields[$className])) {
            $fields = [];

            foreach (static::$_classFields[$className] as $field => $options) {
                if (is_string($field)) {
                    if (is_array($options)) {
                        $fields[$field] = array_merge($optionsMask, static::$fieldDefaults, ['columnName' => $field], $options);
                    } elseif (is_string($options)) {
                        $fields[$field] = array_merge($optionsMask, static::$fieldDefaults, ['columnName' => $field, 'type' => $options]);
                    } elseif ($options == null) {
                        continue;
                    }
                } elseif (is_string($options)) {
                    $field = $options;
                    $fields[$field] = array_merge($optionsMask, static::$fieldDefaults, ['columnName' => $field]);
                }

                if ($field == 'Class') {
                    // apply Class enum values
                    $fields[$field]['values'] = static::$subClasses;
                }

                if (!isset($fields[$field]['blankisnull']) && empty($fields[$field]['notnull'])) {
                    $fields[$field]['blankisnull'] = true;
                }

                if ($fields[$field]['autoincrement']) {
                    $fields[$field]['primary'] = true;
                }
            }

            static::$_classFields[$className] = $fields;
        }
    }


    /**
     * Returns class name for instantiating given record
     * @param array $record record
     * @return string class name
     */
    protected static function _getRecordClass($record)
    {
        $static = get_called_class();

        if (!static::fieldExists('Class')) {
            return $static;
        }

        $columnName = static::_cn('Class');

        if (!empty($record[$columnName]) && is_subclass_of($record[$columnName], $static)) {
            return $record[$columnName];
        } else {
            return $static;
        }
    }

    /**
     * Shorthand alias for _getColumnName
     * @param string $field name of field
     * @return string column name
     */
    protected static function _cn($field)
    {
        return static::getColumnName($field);
    }


    /**
     * Retrieves given field's value
     * @param string $field Name of field
     * @return mixed value
     */
    protected function _getFieldValue($field, $useDefault = true)
    {
        $fieldOptions = static::$_classFields[get_called_class()][$field];

        if (isset($this->_record[$fieldOptions['columnName']])) {
            $value = $this->_record[$fieldOptions['columnName']];

            // apply type-dependent transformations
            switch ($fieldOptions['type']) {
                case 'password':
                    {
                        return $value;
                    }

                case 'timestamp':
                    {
                        if (!isset($this->_convertedValues[$field])) {
                            if ($value && $value != '0000-00-00 00:00:00') {
                                $this->_convertedValues[$field] = strtotime($value);
                            } else {
                                $this->_convertedValues[$field] = null;
                            }
                        }

                        return $this->_convertedValues[$field];
                    }
                case 'serialized':
                    {
                        if (!isset($this->_convertedValues[$field])) {
                            $this->_convertedValues[$field] = is_string($value) ? unserialize($value) : $value;
                        }

                        return $this->_convertedValues[$field];
                    }
                case 'set':
                case 'list':
                    {
                        if (!isset($this->_convertedValues[$field])) {
                            $delim = empty($fieldOptions['delimiter']) ? ',' : $fieldOptions['delimiter'];
                            $this->_convertedValues[$field] = array_filter(preg_split('/\s*'.$delim.'\s*/', $value));
                        }

                        return $this->_convertedValues[$field];
                    }

                case 'int':
                case 'integer':
                case 'uint':
                    if (!isset($this->_convertedValues[$field])) {
                        if (!$fieldOptions['notnull'] && is_null($value)) {
                            $this->_convertedValues[$field] = $value;
                        } else {
                            $this->_convertedValues[$field] = intval($value);
                        }
                    }
                    return $this->_convertedValues[$field];

                case 'boolean':
                    {
                        if (!isset($this->_convertedValues[$field])) {
                            $this->_convertedValues[$field] = (bool)$value;
                        }

                        return $this->_convertedValues[$field];
                    }

                default:
                    {
                        return $value;
                    }
            }
        } elseif ($useDefault && isset($fieldOptions['default'])) {
            // return default
            return $fieldOptions['default'];
        } else {
            switch ($fieldOptions['type']) {
                case 'set':
                case 'list':
                    {
                        return [];
                    }
                default:
                    {
                        return null;
                    }
            }
        }
    }

    /**
     * Sets given field's value
     * @param string $field Name of field
     * @param mixed $value New value
     * @return mixed value
     */
    protected function _setFieldValue($field, $value)
    {
        // ignore setting versioning fields
        if (static::isVersioned()) {
            if (array_key_exists($field, static::$versioningFields)) {
                return false;
            }
        }

        if (!static::fieldExists($field)) {
            return false;
        }
        $fieldOptions = static::$_classFields[get_called_class()][$field];

        // no overriding autoincrements
        if ($fieldOptions['autoincrement']) {
            return false;
        }

        if (!isset($this->fieldSetMapper)) {
            $this->fieldSetMapper = new DefaultSetMapper();
        }

        // pre-process value
        $forceDirty = false;
        switch ($fieldOptions['type']) {
            case 'clob':
            case 'string':
                {
                    $value = $this->fieldSetMapper->setStringValue($value);
                    if (!$fieldOptions['notnull'] && $fieldOptions['blankisnull'] && ($value === '' || $value === null)) {
                        $value = null;
                    }
                    break;
                }

            case 'boolean':
                {
                    $value = $this->fieldSetMapper->setBooleanValue($value);
                    break;
                }

            case 'decimal':
                {
                    $value = $this->fieldSetMapper->setDecimalValue($value);
                    break;
                }

            case 'int':
            case 'uint':
            case 'integer':
                {
                    $value = $this->fieldSetMapper->setIntegerValue($value);
                    if (!$fieldOptions['notnull'] && ($value === '' || is_null($value))) {
                        $value = null;
                    }
                    break;
                }

            case 'timestamp':
                {
                    $value = $this->fieldSetMapper->setTimestampValue($value);
                    break;
                }

            case 'date':
                {
                    $value = $this->fieldSetMapper->setDateValue($value);
                    break;
                }

                // these types are converted to strings from another PHP type on save
            case 'serialized':
                {
                    $this->_convertedValues[$field] = $value;
                    $value = $this->fieldSetMapper->setSerializedValue($value);
                    break;
                }
            case 'enum':
                {
                    $value = $this->fieldSetMapper->setEnumValue($fieldOptions['values'], $value);
                    break;
                }
            case 'set':
            case 'list':
                {
                    $value = $this->fieldSetMapper->setListValue($value, isset($fieldOptions['delimiter']) ? $fieldOptions['delimiter'] : null);
                    $this->_convertedValues[$field] = $value;
                    $forceDirty = true;
                    break;
                }
        }

        if ($forceDirty || (empty($this->_record[$field]) && isset($value)) || ($this->_record[$field] !== $value)) {
            $columnName = static::_cn($field);
            if (isset($this->_record[$columnName])) {
                $this->_originalValues[$field] = $this->_record[$columnName];
            }
            $this->_record[$columnName] = $value;
            $this->_isDirty = true;

            // unset invalidated relationships
            if (!empty($fieldOptions['relationships']) && static::isRelational()) {
                foreach ($fieldOptions['relationships'] as $relationship => $isCached) {
                    if ($isCached) {
                        unset($this->_relatedObjects[$relationship]);
                    }
                }
            }
            return true;
        } else {
            return false;
        }
    }

    protected function _prepareRecordValues()
    {
        $record = [];

        foreach (static::$_classFields[get_called_class()] as $field => $options) {
            $columnName = static::_cn($field);

            if (array_key_exists($columnName, $this->_record)) {
                $value = $this->_record[$columnName];

                if (!$value && !empty($options['blankisnull'])) {
                    $value = null;
                }
            } elseif (isset($options['default'])) {
                $value = $options['default'];
            } else {
                continue;
            }

            if (($options['type'] == 'date') && ($value == '0000-00-00') && !empty($options['blankisnull'])) {
                $value = null;
            }
            if (($options['type'] == 'timestamp')) {
                if (is_numeric($value)) {
                    $value = date('Y-m-d H:i:s', $value);
                } elseif ($value == null && !$options['notnull']) {
                    $value = null;
                }
            }

            if (($options['type'] == 'serialized') && !is_string($value)) {
                $value = serialize($value);
            }

            if (($options['type'] == 'list') && is_array($value)) {
                $delim = empty($options['delimiter']) ? ',' : $options['delimiter'];
                $value = implode($delim, $value);
            }

            $record[$field] = $value;
        }

        return $record;
    }

    protected static function _mapValuesToSet($recordValues)
    {
        $set = [];

        foreach ($recordValues as $field => $value) {
            $fieldConfig = static::$_classFields[get_called_class()][$field];

            if ($value === null) {
                $set[] = sprintf('`%s` = NULL', $fieldConfig['columnName']);
            } elseif ($fieldConfig['type'] == 'timestamp' && $value == 'CURRENT_TIMESTAMP') {
                $set[] = sprintf('`%s` = CURRENT_TIMESTAMP', $fieldConfig['columnName']);
            } elseif ($fieldConfig['type'] == 'set' && is_array($value)) {
                $set[] = sprintf('`%s` = "%s"', $fieldConfig['columnName'], DB::escape(join(',', $value)));
            } elseif ($fieldConfig['type'] == 'boolean') {
                $set[] = sprintf('`%s` = %u', $fieldConfig['columnName'], $value ? 1 : 0);
            } else {
                $set[] = sprintf('`%s` = "%s"', $fieldConfig['columnName'], DB::escape($value));
            }
        }

        return $set;
    }

    protected static function _mapFieldOrder($order)
    {
        if (is_string($order)) {
            return [$order];
        } elseif (is_array($order)) {
            $r = [];

            foreach ($order as $key => $value) {
                if (is_string($key)) {
                    $columnName = static::_cn($key);
                    $direction = strtoupper($value)=='DESC' ? 'DESC' : 'ASC';
                } else {
                    $columnName = static::_cn($value);
                    $direction = 'ASC';
                }

                $r[] = sprintf('`%s` %s', $columnName, $direction);
            }

            return $r;
        }
    }

    protected static function _mapConditions($conditions)
    {
        foreach ($conditions as $field => &$condition) {
            if (is_string($field)) {
                if (isset(static::$_classFields[get_called_class()][$field])) {
                    $fieldOptions = static::$_classFields[get_called_class()][$field];
                }

                if ($condition === null || ($condition == '' && $fieldOptions['blankisnull'])) {
                    $condition = sprintf('`%s` IS NULL', static::_cn($field));
                } elseif (is_array($condition)) {
                    $condition = sprintf('`%s` %s "%s"', static::_cn($field), $condition['operator'], DB::escape($condition['value']));
                } else {
                    $condition = sprintf('`%s` = "%s"', static::_cn($field), DB::escape($condition));
                }
            }
        }

        return $conditions;
    }

    protected function finishValidation()
    {
        $this->_isValid = $this->_isValid && !$this->_validator->hasErrors();

        if (!$this->_isValid) {
            $this->_validationErrors = array_merge($this->_validationErrors, $this->_validator->getErrors());
        }

        return $this->_isValid;
    }
}
