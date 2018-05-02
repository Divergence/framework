<?php
namespace Divergence\Models;

use Exception;
use Divergence\Helpers\Util as Util;
use Divergence\IO\Database\SQL as SQL;

use Divergence\IO\Database\MySQL as DB;

class ActiveRecord
{
    // configurables
    /**
    * Set this to true if you want the table(s) to automatically be created when not found.
    * @var bool
    */
    public static $autoCreateTables = true;
    
    /**
     * Name of table
     * @var string
     */
    public static $tableName = 'records';
    
    /**
     * Noun to describe singular object
     * @var string
     */
    public static $singularNoun = 'record';
    
    /**
     * Noun to describe a plurality of objects
     * @var string
     */
    public static $pluralNoun = 'records';
    
    /**
     * String to identify this class with in administrative interfaces
     * @var string
     */
    public static $classTitle = 'Untitled Class';
    
    /**
     * Defaults values for field definitions
     * @var array
     */
    public static $fieldDefaults = [
        'type' => 'string'
        ,'notnull' => true,
    ];
    
    /**
     * Field definitions
     * @var array
     */
    public static $fields = [];
    
    /**
     * Index definitions
     * @var array
     */
    public static $indexes = [];
    
    
    /**
     * Relationship definitions
     * @var array
     */
    public static $relationships = [];
    
    /**
     * Map of existing tables
     * @var array
     */
    public static $tables;
    
    /**
     * Class names of possible contexts
     * @var array
     */
    public static $contextClasses;
    
    /**
     * Default conditions for get* operations
     * @var array
     */
    public static $defaultConditions = [];
    
    public static $primaryKey = null;
    
    // support subclassing
    public static $rootClass = null;
    public static $defaultClass = null;
    public static $subClasses = [];
    
    // versioning
    public static $historyTable;
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;
    
    // callbacks
    public static $beforeSave;
    public static $afterSave;

    // protected members
    protected static $_classFields = [];
    protected static $_classRelationships = [];
    protected static $_classBeforeSave = [];
    protected static $_classAfterSave = [];
    
    // class members subclassing
    protected static $_fieldsDefined = [];
    protected static $_relationshipsDefined = [];
    protected static $_eventsDefined = [];
    
    protected $_record;
    protected $_convertedValues;
    protected $_relatedObjects;
    protected $_isDirty;
    protected $_isPhantom;
    protected $_wasPhantom;
    protected $_isValid;
    protected $_isNew;
    protected $_isUpdated;
    protected $_validator;
    protected $_validationErrors;
    protected $_originalValues;

    public function __construct($record = [], $isDirty = false, $isPhantom = null)
    {
        $this->_record = $record;
        $this->_relatedObjects = [];
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
    
    public function __get($name)
    {
        return $this->getValue($name);
    }
    
    public function __set($name, $value)
    {
        return $this->setValue($name, $value);
    }
    
    public function __isset($name)
    {
        $value = $this->getValue($name);
        return isset($value);
    }
    
    public function getPrimaryKey()
    {
        if (isset(static::$primaryKey)) {
            return $this->__get(static::$primaryKey);
        } else {
            return $this->ID;
        }
    }
    
    public static function init()
    {
        $className = get_called_class();
        if (!static::$_fieldsDefined[$className]) {
            static::_defineFields();
            static::_initFields();
            
            static::$_fieldsDefined[$className] = true;
        }
        if (!static::$_relationshipsDefined[$className] && static::isRelational()) {
            static::_defineRelationships();
            static::_initRelationships();
            
            static::$_fieldsDefined[$className] = true;
        }
        
        if (!static::$_eventsDefined[$className]) {
            static::_defineEvents();
            
            static::$_eventsDefined[$className] = true;
        }
    }
    
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
                elseif ($name == 'Handle') {
                    return $this->ID;
                }
                // handle a dot-path to related record field
                elseif (count($path = explode('.', $name)) >= 2 && static::_relationshipExists($path[0])) {
                    $related = $this->_getRelationshipValue(array_shift($path));

                    while (is_array($related)) {
                        $related = $related[array_shift($path)];
                    }

                    return is_object($related) ? $related->getValue(implode('.', $path)) : $related;
                }
                // undefined
                else {
                    return null;
                }
            }
        }
    }
    
    public function setValue($name, $value)
    {
        // handle field
        if (static::fieldExists($name)) {
            $this->_setFieldValue($name, $value);
        }
        // handle relationship
        elseif (static::isRelational()) {
            if (static::_relationshipExists($name)) {
                $this->_setRelationshipValue($name, $value);
            }
        }
        // undefined
        else {
            return false;
        }
    }
    
    public static function isVersioned()
    {
        return in_array('Divergence\\Models\\Versioning', class_uses(get_called_class()));
    }
    
    public static function isRelational()
    {
        return in_array('Divergence\\Models\\Relations', class_uses(get_called_class()));
    }
    
    public static function create($values = [], $save = false)
    {
        $className = get_called_class();
        
        // create class
        $ActiveRecord = new $className();
        $ActiveRecord->setFields($values);
        
        if ($save) {
            $ActiveRecord->save();
        }
        
        return $ActiveRecord;
    }
    
    
    public function isA($class)
    {
        return is_a($this, $class);
    }
    
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
    
    public function setFields($values)
    {
        foreach ($values as $field => $value) {
            $this->_setFieldValue($field, $value);
        }
    }
    
    public function setField($field, $value)
    {
        $this->_setFieldValue($field, $value);
    }
    
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
    
    public function isFieldDirty($field)
    {
        return $this->isPhantom || array_key_exists($field, $this->_originalValues);
    }
    
    public function getOriginalValue($field)
    {
        return $this->_originalValues[$field];
    }
    
    public function save($deep = true)
    {
        
        // run before save
        foreach (static::$_classBeforeSave as $beforeSave) {
            if (is_callable($beforeSave)) {
                $beforeSave($this);
            }
        }
        
        if (static::isVersioned()) {
            $wasDirty = false;
        
            if ($this->isDirty && static::$createRevisionOnSave) {
                // update creation time
                $this->Created = time();
                
                $wasDirty = true;
            }
        }
        
        
        // set created
        if (static::fieldExists('Created') && (!$this->Created || ($this->Created == 'CURRENT_TIMESTAMP'))) {
            $this->Created = time();
        }
        
        // validate
        if (!$this->validate($deep)) {
            throw new RecordValidationException($this, 'Cannot save invalid record');
        }
        
        // clear caches
        foreach ($this->getClassFields() as $field => $options) {
            if (!empty($options['unique']) || !empty($options['primary'])) {
                $key = sprintf('%s/%s:%s', static::$tableName, $field, $this->getValue($field));
                DB::clearCachedRecord($key);
            }
        }
        
        // traverse relationships
        if ($deep && static::isRelational()) {
            $this->_saveRelationships();
        }

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
                        static::$tableName
                        , join(',', $set),
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
                        static::$tableName
                        , join(',', $set)
                        , static::_cn(static::$primaryKey ? static::$primaryKey : 'ID')
                        , $this->getPrimaryKey(),
                    ],
                    [static::class,'handleError']
                );
                
                $this->_isUpdated = true;
            }
            
            // update state
            $this->_isDirty = false;
            
            if (static::isVersioned()) {
                if ($wasDirty && static::$createRevisionOnSave) {
                    // save a copy to history table
                    $recordValues = $this->_prepareRecordValues();
                    $set = static::_mapValuesToSet($recordValues);
            
                    DB::nonQuery(
                        'INSERT INTO `%s` SET %s',
            
                        [
                            static::getHistoryTable()
                            , join(',', $set),
                        ]
                    );
                }
            }
        }
        
        // run after save
        foreach (static::$_classAfterSave as $afterSave) {
            if (is_callable($afterSave)) {
                $afterSave($this);
            }
        }
        
        // traverse relationships again
        if ($deep && static::isRelational()) {
            $this->_postSaveRelationships();
        }
    }
    
    
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
                                static::getHistoryTable()
                                , join(',', $set),
                        ]
                );
            }
        }
        
        return static::delete($this->getPrimaryKey());
    }
    
    public static function delete($id)
    {
        DB::nonQuery('DELETE FROM `%s` WHERE `%s` = %u', [
            static::$tableName
            ,static::_cn(static::$primaryKey ? static::$primaryKey : 'ID')
            ,$id,
        ], [static::class,'handleError']);
        
        return DB::affectedRows() > 0;
    }
    
    public static function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return static::getByContext($Record::$rootClass, $Record->getPrimaryKey(), $options);
    }
    
    public static function getByContext($contextClass, $contextID, $options = [])
    {
        if (!static::fieldExists('ContextClass')) {
            throw new \Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = Util::prepareOptions($options, [
            'conditions' => []
            ,'order' => false,
        ]);
        
        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;
    
        $record = static::getRecordByWhere($options['conditions'], $options);

        $className = static::_getRecordClass($record);
        
        return $record ? new $className($record) : null;
    }
    
    public static function getByHandle($handle)
    {
        return static::getByID($handle);
    }
    
    public static function getByID($id)
    {
        $record = static::getRecordByField(static::$primaryKey ? static::$primaryKey : 'ID', $id, true);
        
        return static::instantiateRecord($record);
    }
        
    public static function getByField($field, $value, $cacheIndex = false)
    {
        $record = static::getRecordByField($field, $value, $cacheIndex);
        
        return static::instantiateRecord($record);
    }
    
    public static function getRecordByField($field, $value, $cacheIndex = false)
    {
        $query = 'SELECT * FROM `%s` WHERE `%s` = "%s" LIMIT 1';
        $params = [
            static::$tableName
            , static::_cn($field)
            , DB::escape($value),
        ];
    
        if ($cacheIndex) {
            $key = sprintf('%s/%s:%s', static::$tableName, $field, $value);
            return DB::oneRecordCached($key, $query, $params, [static::class,'handleError']);
        } else {
            return DB::oneRecord($query, $params, [static::class,'handleError']);
        }
    }
    
    public static function getByWhere($conditions, $options = [])
    {
        $record = static::getRecordByWhere($conditions, $options);
        
        return static::instantiateRecord($record);
    }
    
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
                static::$tableName
                , join(') AND (', $conditions)
                , $order ? 'ORDER BY '.join(',', $order) : '',
            ],
        
            [static::class,'handleError']
        );
    }
    
    public static function getByQuery($query, $params = [])
    {
        return static::instantiateRecord(DB::oneRecord($query, $params, [static::class,'handleError']));
    }

    public static function getAllByClass($className = false, $options = [])
    {
        return static::getAllByField('Class', $className ? $className : get_called_class(), $options);
    }
    
    public static function getAllByContextObject(ActiveRecord $Record, $options = [])
    {
        return static::getAllByContext($Record::$rootClass, $Record->getPrimaryKey(), $options);
    }

    public static function getAllByContext($contextClass, $contextID, $options = [])
    {
        if (!static::fieldExists('ContextClass')) {
            throw new \Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = Util::prepareOptions($options, [
            'conditions' => [],
        ]);
        
        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;
    
        return static::instantiateRecords(static::getAllRecordsByWhere($options['conditions'], $options));
    }
    
    public static function getAllByField($field, $value, $options = [])
    {
        return static::getAllByWhere([$field => $value], $options);
    }
        
    public static function getAllByWhere($conditions = [], $options = [])
    {
        return static::instantiateRecords(static::getAllRecordsByWhere($conditions, $options));
    }
    
    public static function getAllRecordsByWhere($conditions = [], $options = [])
    {
        $className = get_called_class();
    
        $options = Util::prepareOptions($options, [
            'indexField' => false
            ,'order' => false
            ,'limit' => false
            ,'offset' => 0
            ,'calcFoundRows' => !empty($options['limit'])
            ,'joinRelated' => false
            ,'extraColumns' => false
            ,'having' => false,
        ]);

        
        // handle joining related tables
        if (static::isRelational()) {
            $join = '';
            if ($options['joinRelated']) {
                if (is_string($options['joinRelated'])) {
                    $options['joinRelated'] = [$options['joinRelated']];
                }
                
                // prefix any conditions
                
                foreach ($options['joinRelated'] as $relationship) {
                    if (!$rel = static::$_classRelationships[get_called_class()][$relationship]) {
                        die("joinRelated specifies a relationship that does not exist: $relationship");
                    }
                                    
                    switch ($rel['type']) {
                        case 'one-one':
                        {
                            $join .= sprintf(' JOIN `%1$s` AS `%2$s` ON(`%2$s`.`%3$s` = `%4$s`)', $rel['class']::$tableName, $relationship::$rootClass, $rel['foreign'], $rel['local']);
                            break;
                        }
                        default:
                        {
                            die("getAllRecordsByWhere does not support relationship type $rel[type]");
                        }
                    }
                }
            }
        } // isRelational
        
        // initialize conditions
        if ($conditions) {
            if (is_string($conditions)) {
                $conditions = [$conditions];
            }
        
            $conditions = static::_mapConditions($conditions);
        }
        
        // build query
        $query  = 'SELECT %1$s `%3$s`.*';
        
        if (!empty($options['extraColumns'])) {
            if (is_array($options['extraColumns'])) {
                foreach ($options['extraColumns'] as $key => $value) {
                    $query .= ', '.$value.' AS '.$key;
                }
            } else {
                $query .= ', ' . $options['extraColumns'];
            }
        }
        $query .= ' FROM `%2$s` AS `%3$s` %4$s';
        $query .= ' WHERE (%5$s)';
        
        if (!empty($options['having'])) {
            $query .= ' HAVING (' . (is_array($options['having']) ? join(') AND (', static::_mapConditions($options['having'])) : $options['having']) . ')';
        }
        
        $params = [
            $options['calcFoundRows'] ? 'SQL_CALC_FOUND_ROWS' : ''
            , static::$tableName
            , $className::$rootClass
            , $join
            , $conditions ? join(') AND (', $conditions) : '1',
        ];
        
        

        if ($options['order']) {
            $query .= ' ORDER BY ' . join(',', static::_mapFieldOrder($options['order']));
        }
        
        if ($options['limit']) {
            $query .= sprintf(' LIMIT %u,%u', $options['offset'], $options['limit']);
        }
        
        if ($options['indexField']) {
            return DB::table(static::_cn($options['indexField']), $query, $params, [static::class,'handleError']);
        } else {
            return DB::allRecords($query, $params, [static::class,'handleError']);
        }
    }
    
    public static function getAll($options = [])
    {
        return static::instantiateRecords(static::getAllRecords($options));
    }
    
    public static function getAllRecords($options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false
            ,'order' => false
            ,'limit' => false
            ,'calcFoundRows' => false
            ,'offset' => 0,
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
            return DB::table(static::_cn($options['indexField']), $query, $params, [static::class,'handleError']);
        } else {
            return DB::allRecords($query, $params, [static::class,'handleError']);
        }
    }
    
    public static function getAllByQuery($query, $params = [])
    {
        return static::instantiateRecords(DB::allRecords($query, $params, [static::class,'handleError']));
    }

    public static function getTableByQuery($keyField, $query, $params)
    {
        return static::instantiateRecords(DB::table($keyField, $query, $params, [static::class,'handleError']));
    }

    
    
    public static function instantiateRecord($record)
    {
        $className = static::_getRecordClass($record);
        return $record ? new $className($record) : null;
    }
    
    public static function instantiateRecords($records)
    {
        foreach ($records as &$record) {
            $className = static::_getRecordClass($record);
            $record = new $className($record);
        }
        
        return $records;
    }
    
    public static function getUniqueHandle($text, $options = [])
    {
        // apply default options
        $options = Util::prepareOptions($options, [
            'handleField' => 'Handle'
            ,'domainConstraints' => []
            ,'alwaysSuffix' => false
            ,'format' => '%s:%u',
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
        
        $where = $options['domainConstraints'];
        
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
    
    public static function generateRandomHandle($length = 32)
    {
        // apply default options
        $options = Util::prepareOptions($options, [
            'handleField' => 'Handle',
        ]);
    
        do {
            $handle = substr(md5(mt_rand(0, mt_getrandmax())), 0, $length);
        } while (static::getByField($options['handleField'], $handle));
        
        return $handle;
    }
    
    public static function fieldExists($field)
    {
        static::init();
        return array_key_exists($field, static::$_classFields[get_called_class()]);
    }
    
    
    public static function getClassFields()
    {
        static::init();
        return static::$_classFields[get_called_class()];
    }
    
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
    
    public function getRootClass()
    {
        return static::$rootClass;
    }
    
    public function addValidationErrors($array)
    {
        foreach ($array as $field => $errorMessage) {
            $this->addValidationError($field, $errorMessage);
        }
    }

    public function addValidationError($field, $errorMessage)
    {
        $this->_isValid = false;
        $this->_validationErrors[$field] = $errorMessage;
    }
    
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
    
    
    public function validate($deep = true)
    {
        $this->_isValid = true;
        $this->_validationErrors = [];
        
        if (!isset($this->_validator)) {
            $this->_validator = new RecordValidator($this->_record);
        } else {
            $this->_validator->resetErrors();
        }
        
        $this->finishValidation();

        if ($deep) {
            // validate relationship objects
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
            }
        }
        
        return $this->_isValid;
    }
    
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
                self::handleError($query, $queryLog, $errorHandler);
            }
            
            // clear buffer (required for the next query to work without running fetchAll first
            $Statement->closeCursor();
            
            return $Connection->query($query); // now the query should finish with no error
        } else {
            return DB::handleError($query, $queryLog);
        }
    }
    
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
     * Called when a class is loaded to define fields before _initFields
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
     * Must be idempotent as it may be applied multiple times up the inheritence chain
     */
    protected static function _initFields()
    {
        $className = get_called_class();
        $optionsMask = [
            'type' => null
            ,'length' => null
            ,'primary' => null
            ,'unique' => null
            ,'autoincrement' => null
            ,'notnull' => null
            ,'unsigned' => null
            ,'default' => null
            ,'values' => null,
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
                
                case 'boolean':
                {
                    if (!isset($this->_convertedValues[$field])) {
                        $this->_convertedValues[$field] = (boolean)$value;
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
            // set relationship
            if (static::isRelational()) {
                if (static::_relationshipExists($field)) {
                    return $this->_setRelationshipValue($field, $value);
                } else {
                    return false;
                }
            }
        }
        $fieldOptions = static::$_classFields[get_called_class()][$field];

        // no overriding autoincrements
        if ($fieldOptions['autoincrement']) {
            return false;
        }

        // pre-process value
        $forceDirty = false;
        switch ($fieldOptions['type']) {
            case 'clob':
            case 'string':
            {
                if (!$fieldOptions['notnull'] && $fieldOptions['blankisnull'] && ($value === '' || $value === null)) {
                    $value = null;
                    break;
                }
            
                // normalize encoding to ASCII
                $value = @mb_convert_encoding($value, DB::$encoding, 'auto');
                
                break;
            }
            
            case 'boolean':
            {
                $value = (boolean)$value;
            }
            
            case 'decimal':
            {
                $value = preg_replace('/[^-\d.]/', '', $value);
                break;
            }
            
            case 'int':
            case 'uint':
            case 'integer':
            {
                $value = preg_replace('/[^-\d]/', '', $value);
                
                if (!$fieldOptions['notnull'] && $value === '') {
                    $value = null;
                }
                
                break;
            }
            
            case 'timestamp':
            {
                if (is_numeric($value)) {
                    $value = date('Y-m-d H:i:s', $value);
                } elseif (is_string($value)) {
                    // trim any extra crap, or leave as-is if it doesn't fit the pattern
                    if (preg_match('/^(\d{4})\D?(\d{2})\D?(\d{2})T?(\d{2})\D?(\d{2})\D?(\d{2})/')) {
                        $value = preg_replace('/^(\d{4})\D?(\d{2})\D?(\d{2})T?(\d{2})\D?(\d{2})\D?(\d{2})/', '$1-$2-$3 $4:$5:$6', $value);
                    } else {
                        $value = date('Y-m-d H:i:s', strtotime($value));
                    }
                }
                break;
            }
            
            case 'date':
            {
                if (is_numeric($value)) {
                    $value = date('Y-m-d', $value);
                } elseif (is_string($value)) {
                    // check if m/d/y format
                    if (preg_match('/^(\d{2})\D?(\d{2})\D?(\d{4}).*/', $value)) {
                        $value = preg_replace('/^(\d{2})\D?(\d{2})\D?(\d{4}).*/', '$3-$1-$2', $value);
                    }
                    
                    // trim time and any extra crap, or leave as-is if it doesn't fit the pattern
                    $value = preg_replace('/^(\d{4})\D?(\d{2})\D?(\d{2}).*/', '$1-$2-$3', $value);
                } elseif (is_array($value) && count(array_filter($value))) {
                    // collapse array date to string
                    $value = sprintf(
                        '%04u-%02u-%02u',
                        is_numeric($value['yyyy']) ? $value['yyyy'] : 0,
                        is_numeric($value['mm']) ? $value['mm'] : 0,
                        is_numeric($value['dd']) ? $value['dd'] : 0
                    );
                } else {
                    if ($value = strtotime($value)) {
                        $value = date('Y-m-d', $value) ?: null;
                    } else {
                        $value = null;
                    }
                }
                break;
            }
            
            // these types are converted to strings from another PHP type on save
            case 'serialized' :
            {
                $this->_convertedValues[$field] = $value;
                $value = serialize($value);
                break;
            }
            case 'enum':
            {
                $value = in_array($value, $fieldOptions['values']) ? $value : null;
                break;
            }
            case 'set':
            case 'list':
            {
                if (!is_array($value)) {
                    $delim = empty($fieldOptions['delimiter']) ? ',' : $fieldOptions['delimiter'];
                    $value = array_filter(preg_split('/\s*'.$delim.'\s*/', $value));
                }
            
                $this->_convertedValues[$field] = $value;
                $forceDirty = true;
                break;
            }
        }
        
        if ($forceDirty || ($this->_record[$field] !== $value)) {
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
                $fieldOptions = static::$_classFields[get_called_class()][$field];
            
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
