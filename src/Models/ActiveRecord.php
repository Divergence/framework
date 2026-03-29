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
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use JsonSerializable;
use Divergence\Models\Mapping\Column;
use Divergence\Models\Events\Save as SaveHandler;
use Divergence\Models\Events\HandleException as HandleExceptionHandler;
use Divergence\Models\Events\Delete as DeleteHandler;
use Divergence\Models\Events\Destroy as DestroyHandler;
use Divergence\Models\Events\AfterSave as AfterSaveHandler;
use Divergence\Models\Events\BeforeSave as BeforeSaveHandler;
use Divergence\Models\Events\ClearCaches as ClearCachesHandler;
use Divergence\Models\RecordValidator;
use Divergence\IO\Database\Connections;
use Divergence\IO\Database\StorageType;
use Divergence\Models\Mapping\Relation;
use Divergence\IO\Database\Query\Insert;
use Divergence\IO\Database\Query\Update;
use Divergence\Models\Mapping\DefaultGetMapper;
use Divergence\Models\Mapping\DefaultSetMapper;

/**
 * ActiveRecord
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
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
 * @property array $versioningFields
 * @property-read array $_relatedObjects Relationship cache
 *
 * @method static void _defineRelationships()
 * @method static void _initRelationships()
 * @method static bool _relationshipExists(string $value)
 * @method static array<ActiveRecord>|ActiveRecord|null _getRelationshipValue(string $value)
 * @method void beforeVersionedSave()
 * @method void afterVersionedSave()
 * @method static string getHistoryTable()
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
    public static $singularNoun = null;

    /**
     *
     * @var string $pluralNoun Noun to describe a plurality of objects
     */
    public static $pluralNoun = null;

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
    public static $clearCachesHandler = ClearCachesHandler::class;
    public static $beforeSaveHandler = BeforeSaveHandler::class;
    public static $afterSaveHandler = AfterSaveHandler::class;
    public static $saveHandler = SaveHandler::class;
    public static $destroyHandler = DestroyHandler::class;
    public static $deleteHandler = DeleteHandler::class;
    public static $handleExceptionHandler = HandleExceptionHandler::class;

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
    protected static $_isVersioned = [];
    protected static $_isRelational = [];
    protected static $_resolvedRootClasses = [];
    protected static $_resolvedDefaultClasses = [];
    protected static $_resolvedSubClasses = [];
    protected static $_resolvedSingularNouns = [];
    protected static $_resolvedPluralNouns = [];

    /**
     * @var array<string, array<string, ReflectionProperty|false>>
     */
    protected static $_attributeProperties = [];

    /**
     * @var array $_record Raw array data for this model.
     */
    protected array $_record = [] {
        set (array $value) {
            $this->_record = $value;
            if (empty($this->_suppressRecordSynchronization)) {
                $this->synchronizeAuthoritativePropertiesFromRecord();
            }
        }
    }

    /**
     * @var array $_convertedValues Raw array data for this model of data normalized for it's field type.
     */
    protected $_convertedValues;

    /**
     * @var RecordValidator $_validator Instance of a RecordValidator object.
     */
    protected $_validator;

    /**
     * Internal helper flag so targeted record writes don't trigger a full-property resync through the set hook.
     *
     * @var bool
     */
    protected $_suppressRecordSynchronization = false;

    /**
     * Validation works on a plain array buffer because hooked properties cannot be passed by reference.
     *
     * @var array
     */
    protected $_validatorRecord = [];

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

    /**
     * Cached SET payload for immediate follow-up persistence steps like version history.
     *
     * @var array|null
     */
    protected $_preparedPersistedSet = null;


    public const defaultSetMapper = DefaultSetMapper::class;
    public const defaultGetMapper = DefaultGetMapper::class;
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
            $this->_setFieldValue('Class', get_class($this));
        }

        $this->initializeAttributeFields();

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
    public static function getPrimaryKey()
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
            return $this->{static::$primaryKey} ?? $this->_getFieldValue(static::$primaryKey);
        } else {
            return $this->_getFieldValue('ID');
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
        $className = get_called_class();
        switch ($name) {
            case 'isDirty':
                $value = $this->_isDirty;
                break;

            case 'isPhantom':
                $value = $this->_isPhantom;
                break;

            case 'wasPhantom':
                $value = $this->_wasPhantom;
                break;

            case 'isValid':
                $value = $this->_isValid;
                break;

            case 'isNew':
                $value = $this->_isNew;
                break;

            case 'isUpdated':
                $value = $this->_isUpdated;
                break;

            case 'validationErrors':
                $value = array_filter($this->_validationErrors);
                break;

            case 'data':
                $value = $this->getData();
                break;

            case 'originalValues':
                $value = $this->_originalValues;
                break;

            default:
                {
                    // handle field
                    if (isset(static::$_classFields[$className][$name])) {
                        $value = $this->_getFieldValue($name);
                    }
                    // handle relationship
                    elseif (!empty(static::$_classRelationships[$className]) && static::_relationshipExists($name)) {
                            $value = $this->_getRelationshipValue($name);
                    }
                    // default Handle to ID if not caught by fieldExists
                    elseif ($name == static::$handleField) {
                        $value = $this->_getFieldValue('ID');
                    } else {
                        $value = null;
                    }
                    break;
                }
        }
        return $value;
    }

    protected static function getAttributeProperty(string $field): ?ReflectionProperty
    {
        $className = get_called_class();

        if (!array_key_exists($field, static::$_attributeProperties[$className] ?? [])) {
            $fieldOptions = static::$_classFields[$className][$field] ?? null;

            if (empty($fieldOptions['attributeField'])) {
                static::$_attributeProperties[$className][$field] = false;
            } else {
                $reflection = new ReflectionClass($className);
                static::$_attributeProperties[$className][$field] = $reflection->hasProperty($field)
                    ? $reflection->getProperty($field)
                    : false;
            }
        }

        return static::$_attributeProperties[$className][$field] ?: null;
    }

    protected static function getAttributeTypeDefaultValue(?ReflectionType $type)
    {
        if ($type === null || $type->allowsNull()) {
            return null;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $namedType) {
                $default = static::getAttributeTypeDefaultValue($namedType);

                if ($default !== null || $namedType->getName() === 'string') {
                    return $default;
                }
            }

            return null;
        }

        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return null;
        }

        return match ($type->getName()) {
            'array' => [],
            'bool' => false,
            'float' => 0.0,
            'int' => 0,
            'string' => '',
            default => null,
        };
    }

    protected function initializeAttributeField(string $field): void
    {
        $property = static::getAttributeProperty($field);

        if (!$property) {
            return;
        }

        $fieldOptions = static::$_classFields[get_called_class()][$field];
        $columnName = $fieldOptions['columnName'];
        $hasValue = array_key_exists($columnName, $this->_record)
            || array_key_exists('default', $fieldOptions)
            || in_array($fieldOptions['type'], ['set', 'list'], true);

        $value = $hasValue
            ? $this->_getFieldValue($field)
            : static::getAttributeTypeDefaultValue($property->getType());

        if ($value === null) {
            $typeDefault = static::getAttributeTypeDefaultValue($property->getType());

            if ($typeDefault !== null || ($property->getType() instanceof ReflectionNamedType && $property->getType()->getName() === 'string')) {
                $value = $typeDefault;
            }
        }

        $property->setValue($this, $value);
    }

    public function initializeAttributeFields(?array $fields = null): void
    {
        static::init();

        $fields = $fields ?: array_keys(static::$_classFields[get_called_class()]);

        foreach ($fields as $field) {
            $this->initializeAttributeField($field);
        }
    }

    protected function synchronizeAuthoritativePropertiesFromRecord(?array $fields = null): void
    {
        if (!method_exists($this, 'initializeAttributeFields')) {
            return;
        }

        $this->initializeAttributeFields($fields);
    }

    protected function setRecordValue(string $columnName, $value): void
    {
        $record = $this->_record;
        $record[$columnName] = $value;
        $this->_suppressRecordSynchronization = true;
        $this->_record = $record;
        $this->_suppressRecordSynchronization = false;
    }

    protected function setRecordValueAndSynchronizeField(string $field, string $columnName, $value): void
    {
        $this->setRecordValue($columnName, $value);
        $this->synchronizeAuthoritativePropertiesFromRecord([$field]);
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
        $className = get_called_class();

        if (!array_key_exists($className, static::$_isVersioned)) {
            static::$_isVersioned[$className] = in_array('Divergence\\Models\\Versioning', class_uses($className));
        }

        return static::$_isVersioned[$className];
    }

    /**
     * Checks if this model is ready for relationships.
     *
     * @return boolean Returns true if this class is defined with Divergence\Models\Relations as a trait.
     */
    public static function isRelational()
    {
        $className = get_called_class();

        if (!array_key_exists($className, static::$_isRelational)) {
            static::$_isRelational[$className] = in_array('Divergence\\Models\\Relations', class_uses($className));
        }

        return static::$_isRelational[$className];
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
    public function isA($class): bool
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

        $this->setRecordValueAndSynchronizeField('Class', static::_cn('Class'), $className);
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
    public function jsonSerialize(): array
    {
        return $this->getData();
    }

    /**
     *  Gets normalized object data.
     *
     *  @return array The model's data as a normal array with any validation errors included.
     */
    public function getData(): array
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
    public function isFieldDirty($field): bool
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
        $handler = static::$clearCachesHandler;
        $handler::handle($this);
    }

    /**
     * Runs the before save event function one at a time for any class that had $beforeSave configured in the ancestor tree.
     */
    public function beforeSave()
    {
        $handler = static::$beforeSaveHandler;
        $handler::handle($this);
    }

    /**
     * Runs the after save event function one at a time for any class that had $beforeSave configured in the ancestor tree. Will only fire if save was successful.
     */
    public function afterSave()
    {
        $handler = static::$afterSaveHandler;
        $handler::handle($this);
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
        $handler = static::$saveHandler;
        $handler::handle($this, $deep);
    }


    /**
     * Deletes this object.
     *
     * @return bool True if database returns number of affected rows above 0. False otherwise.
     */
    public function destroy(): bool
    {
        $handler = static::$destroyHandler;
        return $handler::handle($this);
    }

    /**
     * Delete by ID
     *
     * @param int|string $id
     * @return bool True if database returns number of affected rows above 0. False otherwise.
     */
    public static function delete($id): bool
    {
        $handler = static::$deleteHandler;
        return $handler::handle(static::class, $id);
    }

    /**
     * Checks of a field exists for this model in the fields config.
     *
     * @param string $field Name of the field
     * @return bool True if the field exists. False otherwise.
     */
    public static function fieldExists($field): bool
    {
        static::init();
        return array_key_exists($field, static::$_classFields[get_called_class()]);
    }

    /**
     * Returns the current configuration of class fields for the called class.
     *
     * @return array Current configuration of class fields for the called class.
     */
    public static function getClassFields(): array
    {
        static::init();
        return static::$_classFields[get_called_class()];
    }

    /**
     * Returns either a field option or an array of all the field options.
     *
     * @param string $field Name of the field.
     * @param boolean $optionKey
     * @return array|mixed
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
    public function getRootClass(): string
    {
        return static::getRootClassName();
    }

    public static function getRootClassName(): string
    {
        $className = get_called_class();

        if (!isset(static::$_resolvedRootClasses[$className])) {
            static::$_resolvedRootClasses[$className] = static::hasExplicitStaticOverride($className, 'rootClass') && static::$rootClass
                ? static::$rootClass
                : static::deriveRootClassName($className);
        }

        return static::$_resolvedRootClasses[$className];
    }

    public static function getDefaultClassName(): string
    {
        $className = get_called_class();

        if (!isset(static::$_resolvedDefaultClasses[$className])) {
            static::$_resolvedDefaultClasses[$className] = static::hasExplicitStaticOverride($className, 'defaultClass') && static::$defaultClass
                ? static::$defaultClass
                : $className;
        }

        return static::$_resolvedDefaultClasses[$className];
    }

    public static function getStaticSubClasses(): array
    {
        $className = get_called_class();

        if (!isset(static::$_resolvedSubClasses[$className])) {
            $subClasses = static::hasExplicitStaticOverride($className, 'subClasses') && !empty(static::$subClasses)
                ? static::$subClasses
                : [$className];

            static::$_resolvedSubClasses[$className] = array_values(array_unique($subClasses));
        }

        return static::$_resolvedSubClasses[$className];
    }

    public static function getSingularNoun(): string
    {
        $className = get_called_class();

        if (!isset(static::$_resolvedSingularNouns[$className])) {
            static::$_resolvedSingularNouns[$className] = static::hasExplicitStaticOverride($className, 'singularNoun') && static::$singularNoun
                ? static::$singularNoun
                : static::deriveSingularNoun(static::getRootClassName());
        }

        return static::$_resolvedSingularNouns[$className];
    }

    public static function getPluralNoun(): string
    {
        $className = get_called_class();

        if (!isset(static::$_resolvedPluralNouns[$className])) {
            static::$_resolvedPluralNouns[$className] = static::hasExplicitStaticOverride($className, 'pluralNoun') && static::$pluralNoun
                ? static::$pluralNoun
                : static::pluralizeNoun(static::getSingularNoun());
        }

        return static::$_resolvedPluralNouns[$className];
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

        if (
            empty(static::$validators)
            && (
                !$deep
                || empty(static::$_classRelationships[get_called_class()])
                || empty($this->_relatedObjects)
            )
        ) {
            return true;
        }

        $this->_validatorRecord = $this->_record;
        $this->_validator = new RecordValidator($this->_validatorRecord, false);

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
     * Other errors will be routed through to DB::handleException
     *
     * @param Exception $exception
     * @param string $query
     * @param array $queryLog
     * @param array|string $parameters
     * @return mixed Retried query result or the return from DB::handleException
     */
    public static function handleException(\Exception $e, $query = null, $queryLog = null, $parameters = null)
    {
        $handler = static::$handleExceptionHandler;

        return $handler::handle(static::class, $e, $query, $queryLog, $parameters);
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
            $attributeFields = $class::_definedAttributeFields();
            if (!empty($attributeFields['fields'])) {
                static::$_classFields[$className] = array_merge(static::$_classFields[$className], $attributeFields['fields']);
            }
            if (!empty($attributeFields['relations'])) {
                $class::$relationships = $attributeFields['relations'];
            }
        }
    }

    /**
     * This function grabs all protected fields on the model and uses that as the basis for what constitutes a mapped field
     * It skips a certain list of protected fields that are built in for ORM operation
     *
     * @return array
     */
    public static function _definedAttributeFields(): array
    {
        $fields = [];
        $relations = [];
        $properties = (new ReflectionClass(static::class))->getProperties();
        if (!empty($properties)) {
            foreach ($properties as $property) {
                if ($property->isStatic() || $property->isPublic()) {
                    continue;
                }

                // skip these because they are built in
                if (in_array($property->getName(), [
                    '_classFields','_classRelationships','_classBeforeSave','_classAfterSave','_fieldsDefined','_relationshipsDefined','_eventsDefined','_record','_validator','_validatorRecord'
                    ,'_validationErrors','_isDirty','_isValid','_convertedValues','_originalValues','_isPhantom','_wasPhantom','_isNew','_isUpdated','_relatedObjects','_preparedPersistedSet','_suppressRecordSynchronization'
                ])) {
                    continue;
                }

                $isRelationship = false;

                if ($attributes = $property->getAttributes()) {
                    foreach ($attributes as $attribute) {
                        $attributeName = $attribute->getName();
                        if ($attributeName === Column::class) {
                            $fields[$property->getName()] = static::applyAttributeFieldNullability(
                                $property,
                                array_merge($attribute->getArguments(), ['attributeField' => true])
                            );
                        }

                        if ($attributeName === Relation::class) {
                            $isRelationship = true;
                            $relations[$property->getName()] = $attribute->getArguments();
                        }
                    }
                } else {
                    // default
                    if (!$isRelationship) {
                        $fields[$property->getName()] = static::applyAttributeFieldNullability(
                            $property,
                            ['attributeField' => true]
                        );
                    }
                }
            }
        }
        return [
            'fields' => $fields,
            'relations' => $relations
        ];
    }

    protected static function applyAttributeFieldNullability(ReflectionProperty $property, array $field): array
    {
        if ($type = $property->getType()) {
            $field['notnull'] = !$type->allowsNull();
        }

        return $field;
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
                    $fields[$field]['values'] = static::getStaticSubClasses();
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

    protected static function deriveSingularNoun(string $className): string
    {
        $shortName = (new ReflectionClass($className))->getShortName();
        $noun = preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return strtolower($noun);
    }

    protected static function deriveRootClassName(string $className): string
    {
        $rootClass = $className;
        $reflection = new ReflectionClass($className);

        while ($parent = $reflection->getParentClass()) {
            $parentName = $parent->getName();

            if (in_array($parentName, [self::class, Model::class], true)) {
                break;
            }

            $rootClass = $parentName;
            $reflection = $parent;
        }

        return $rootClass;
    }

    protected static function hasExplicitStaticOverride(string $className, string $property): bool
    {
        $reflection = new ReflectionProperty($className, $property);

        return $reflection->getDeclaringClass()->getName() !== self::class;
    }

    protected static function pluralizeNoun(string $noun): string
    {
        if (preg_match('/[^aeiou]y$/i', $noun)) {
            return substr($noun, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/i', $noun)) {
            return $noun . 'es';
        }

        return $noun . 's';
    }


    private function applyNewValue($type, $field, $value)
    {
        if (!isset($this->_convertedValues[$field])) {
            if (is_null($value) && !in_array($type, ['set','list'])) {
                unset($this->_convertedValues[$field]);
                return null;
            }
            $this->_convertedValues[$field] = $value;
        }
        return $this->_convertedValues[$field];
    }

    /**
     * Applies type-dependent transformations to the value in $this->_record[$fieldOptions['columnName']]
     * Caches to $this->_convertedValues[$field] and returns the value in there.
     * @param string $field Name of field
     * @return mixed value
     */
    protected function _getFieldValue($field, $useDefault = true)
    {
        $fieldOptions = static::$_classFields[get_called_class()][$field];

        if (isset($this->_record[$fieldOptions['columnName']])) {
            $value = $this->_record[$fieldOptions['columnName']];

            $defaultGetMapper = static::defaultGetMapper;

            // apply type-dependent transformations
            switch ($fieldOptions['type']) {
                case 'timestamp':
                        return $this->applyNewValue($fieldOptions['type'], $field, $defaultGetMapper::getTimestampValue($value));

                case 'serialized':
                        return $this->applyNewValue($fieldOptions['type'], $field, $defaultGetMapper::getSerializedValue($value));

                case 'set':
                case 'list':
                        return $this->applyNewValue($fieldOptions['type'], $field, $defaultGetMapper::getListValue($value, $fieldOptions['delimiter'] ?? null));

                case 'int':
                case 'integer':
                case 'uint':
                    return $this->applyNewValue($fieldOptions['type'], $field, $defaultGetMapper::getIntegerValue($value));

                case 'boolean':
                    return $this->applyNewValue($fieldOptions['type'], $field, $defaultGetMapper::getBooleanValue($value));

                case 'decimal':
                    return $this->applyNewValue($fieldOptions['type'], $field, $defaultGetMapper::getDecimalValue($value));

                case 'password':
                default:
                    return $value;
            }
        } elseif ($useDefault && isset($fieldOptions['default'])) {
            // return default
            return $fieldOptions['default'];
        } else {
            switch ($fieldOptions['type']) {
                case 'set':
                case 'list':
                    return [];
                default:
                    return null;
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
            if ($field === 'RevisionID') {
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

        $setMapper = static::defaultSetMapper;

        // pre-process value
        $forceDirty = false;
        switch ($fieldOptions['type']) {
            case 'clob':
            case 'string':
                {
                    $value = $setMapper::setStringValue($value);
                    if (!$fieldOptions['notnull'] && $fieldOptions['blankisnull'] && ($value === '' || $value === null)) {
                        $value = null;
                    }
                    break;
                }

            case 'boolean':
                {
                    $value = $setMapper::setBooleanValue($value);
                    break;
                }

            case 'decimal':
                {
                    $value = $setMapper::setDecimalValue($value);
                    break;
                }

            case 'int':
            case 'uint':
            case 'integer':
                {
                    $value = $setMapper::setIntegerValue($value);
                    if (!$fieldOptions['notnull'] && ($value === '' || is_null($value))) {
                        $value = null;
                    }
                    break;
                }

            case 'timestamp':
                {
                    unset($this->_convertedValues[$field]);
                    $value = $setMapper::setTimestampValue($value);
                    break;
                }

            case 'date':
                {
                    unset($this->_convertedValues[$field]);
                    $value = $setMapper::setDateValue($value);
                    break;
                }

            case 'serialized':
                {
                    if (!is_string($value)) {
                        $value = $setMapper::setSerializedValue($value);
                    }
                    break;
                }
            case 'enum':
                {
                    $value = $setMapper::setEnumValue($fieldOptions['values'], $value);
                    break;
                }
            case 'set':
            case 'list':
                {
                    $value = $setMapper::setListValue($value, isset($fieldOptions['delimiter']) ? $fieldOptions['delimiter'] : null);
                    $this->_convertedValues[$field] = $value;
                    $forceDirty = true;
                    break;
                }
        }

        if ($forceDirty || (empty($this->_record[$field]) && isset($value)) || ($this->_record[$field] !== $value)) {
            $this->_setValueAndMarkDirty($field, $value, $fieldOptions);
            return true;
        } else {
            return false;
        }
    }

    protected function _setValueAndMarkDirty($field, $value, $fieldOptions)
    {
        $columnName = static::_cn($field);
        if (isset($this->_record[$columnName])) {
            $this->_originalValues[$field] = $this->_record[$columnName];
        }

        unset($this->_convertedValues[$field]);
        $this->setRecordValueAndSynchronizeField($field, $columnName, $value);
        $this->_isDirty = true;

        // If a model has been modified we should clear the relationship cache
        // TODO: this can be smarter by only looking at fields that are used in the relationship configuration
        if (!empty($fieldOptions['relationships']) && static::isRelational()) {
            foreach ($fieldOptions['relationships'] as $relationship => $isCached) {
                if ($isCached) {
                    unset($this->_relatedObjects[$relationship]);
                }
            }
        }
    }

    /**
     * @param array<int, string>|null $fields
     * @return array<string, mixed>
     */
    protected function _prepareRecordValues(?array $fields = null)
    {
        $record = [];
        $fields = $fields ?: array_keys(static::$_classFields[get_called_class()]);

        foreach ($fields as $field) {
            $options = static::$_classFields[get_called_class()][$field];
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

    /**
     * @param array<string, array<string, mixed>>|null $fieldConfigs
     * @return array<int, string>
     */
    public function preparePersistedSet(?array $fieldConfigs = null): array
    {
        $set = [];
        $fieldConfigs = $fieldConfigs ?: static::$_classFields[get_called_class()];
        $storageClass = Connections::getConnectionType();
        $record = $this->_record;

        foreach ($fieldConfigs as $field => $options) {
            $columnName = $options['columnName'];

            if (array_key_exists($columnName, $record)) {
                $value = $record[$columnName];

                if (!$value && !empty($options['blankisnull'])) {
                    $value = null;
                }
            } else {
                continue;
            }

            if (($options['type'] == 'date') && ($value == '0000-00-00') && !empty($options['blankisnull'])) {
                $value = null;
            }

            if ($value === null) {
                $set[] = sprintf('`%s` = NULL', $columnName);
                continue;
            }

            if (($options['type'] == 'timestamp')) {
                if ($value == 'CURRENT_TIMESTAMP') {
                    $set[] = sprintf('`%s` = CURRENT_TIMESTAMP', $columnName);
                    continue;
                }

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

            if (($options['type'] == 'set') && is_array($value)) {
                $value = join(',', $value);
            }

            if (in_array($options['type'], ['binary', 'blob'], true) && $storageClass === \Divergence\IO\Database\PostgreSQL::class) {
                $set[] = sprintf('`%s` = %s', $columnName, $storageClass::quote('\\x' . bin2hex($value)));
            } elseif ($options['type'] == 'boolean') {
                $set[] = $storageClass === \Divergence\IO\Database\PostgreSQL::class
                    ? sprintf('`%s` = %s', $columnName, $value ? 'TRUE' : 'FALSE')
                    : sprintf('`%s` = %u', $columnName, $value ? 1 : 0);
            } else {
                $set[] = sprintf('`%s` = %s', $columnName, $storageClass::quote((string) $value));
            }
        }

        return $set;
    }

    /**
     * @param array<string, mixed> $recordValues
     * @param array<string, array<string, mixed>>|null $fieldConfigs
     * @return array<int, string>
     */
    protected static function _mapValuesToSet($recordValues, ?array $fieldConfigs = null)
    {
        $set = [];
        $storageClass = Connections::getConnectionType();

        foreach ($recordValues as $field => $value) {
            $fieldConfig = $fieldConfigs[$field] ?? static::$_classFields[get_called_class()][$field];

            if ($value === null) {
                $set[] = sprintf('`%s` = NULL', $fieldConfig['columnName']);
            } elseif ($fieldConfig['type'] == 'timestamp' && $value == 'CURRENT_TIMESTAMP') {
                $set[] = sprintf('`%s` = CURRENT_TIMESTAMP', $fieldConfig['columnName']);
            } elseif (in_array($fieldConfig['type'], ['binary', 'blob'], true) && $storageClass === \Divergence\IO\Database\PostgreSQL::class) {
                $set[] = sprintf('`%s` = %s', $fieldConfig['columnName'], $storageClass::quote('\\x' . bin2hex($value)));
            } elseif ($fieldConfig['type'] == 'set' && is_array($value)) {
                $value = join(',', $value);
                $set[] = sprintf('`%s` = %s', $fieldConfig['columnName'], $storageClass::quote($value));
            } elseif ($fieldConfig['type'] == 'boolean') {
                $set[] = $storageClass === \Divergence\IO\Database\PostgreSQL::class
                    ? sprintf('`%s` = %s', $fieldConfig['columnName'], $value ? 'TRUE' : 'FALSE')
                    : sprintf('`%s` = %u', $fieldConfig['columnName'], $value ? 1 : 0);
            } else {
                $set[] = sprintf('`%s` = %s', $fieldConfig['columnName'], $storageClass::quote((string) $value));
            }
        }

        return $set;
    }

    /**
     * @param array<int, string>|null $fields
     * @return array<string, mixed>
     */
    public function preparePersistedRecordValues(?array $fields = null): array
    {
        return $this->_prepareRecordValues($fields);
    }

    /**
     * @param array<string, mixed> $recordValues
     * @param array<string, array<string, mixed>>|null $fieldConfigs
     * @return array<int, string>
     */
    public static function mapPreparedValuesToSet(array $recordValues, ?array $fieldConfigs = null): array
    {
        return static::_mapValuesToSet($recordValues, $fieldConfigs);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     */
    public function primeFieldForSave(string $field, $value): void
    {
        unset($this->_convertedValues[$field]);
        $this->setRecordValueAndSynchronizeField($field, static::_cn($field), $value);
    }

    /**
     * @param int|string $insertID
     * @param bool $isIntegerPrimaryKey
     * @return void
     */
    public function finalizeInsert($insertID, bool $isIntegerPrimaryKey = false): void
    {
        if ($isIntegerPrimaryKey) {
            $insertID = intval($insertID);
        }

        $primaryKey = $this->getPrimaryKey();
        unset($this->_convertedValues[$primaryKey]);
        $this->setRecordValueAndSynchronizeField($primaryKey, static::_cn($primaryKey), $insertID);
        $this->_isPhantom = false;
        $this->_isNew = true;
    }

    public function finalizeUpdate(): void
    {
        $this->_isUpdated = true;
    }

    public function finalizeSave(): void
    {
        $this->_isDirty = false;
    }

    /**
     * @param array<int, string> $set
     * @return void
     */
    public function cachePreparedPersistedSet(array $set): void
    {
        $this->_preparedPersistedSet = $set;
    }

    public function getPreparedPersistedSet(): ?array
    {
        return $this->_preparedPersistedSet;
    }

    public function clearPreparedPersistedSet(): void
    {
        $this->_preparedPersistedSet = null;
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

    /**
     * @param array<string,null|string|array{'operator': string, 'value': string}> $conditions
     * @return array
     */
    protected static function _mapConditions($conditions)
    {
        $storageClass = Connections::getConnectionType();

        foreach ($conditions as $field => &$condition) {
            if (is_string($field)) {
                if (isset(static::$_classFields[get_called_class()][$field])) {
                    $fieldOptions = static::$_classFields[get_called_class()][$field];
                }

                if ($condition === null || ($condition == '' && $fieldOptions['blankisnull'])) {
                    $condition = sprintf('`%s` IS NULL', static::_cn($field));
                } elseif (is_array($condition)) {
                    $condition = sprintf('`%s` %s %s', static::_cn($field), $condition['operator'], $storageClass::quote($condition['value']));
                } else {
                    $condition = sprintf('`%s` = %s', static::_cn($field), $storageClass::quote($condition));
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
