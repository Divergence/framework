<?php
namespace Divergence\Tests\MockSite\Models;

class Tag extends \Divergence\Models\Model
{
    //use \Divergence\Models\Versioning;
    //use \Divergence\Models\Relations;
    
    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'tags';
    public static $singularNoun = 'tag';
    public static $pluralNoun = 'tags';
    
    // versioning
    //static public $historyTable = 'test_history';
    //static public $createRevisionOnDestroy = true;
    //static public $createRevisionOnSave = true;
    
    public static $fields = [
        'Tag',
        'Slug',
    ];

    /* expose protected attributes for unit testing */
    public static function getProtected($field) {
        return static::$$field;
    }

    public static function getRecordClass($record) {
        return static::_getRecordClass($record);
    }
}
