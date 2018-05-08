<?php
namespace Divergence\Tests\MockSite\Models\Forum;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Tests\MockSite\Mock\Data;

class Category extends \Divergence\Models\Model
{
    use Versioning;
    use Relations;
    
    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'forum_categories';
    public static $singularNoun = 'categories';
    public static $pluralNoun = 'category';
    
    // versioning
    public static $historyTable = 'forum_categories_history';
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    public static $fields = [
        'Name' => [
            'type' => 'string',
            'required' => true,
            'notnull' => true,
        ],
    ];

    public static $indexes = [];

    public static $relationships = [
        'Threads' => [
            'type' => 'one-many',
            'class' => Thread::class,
            'local' => 'ID',
            'foreign' => 'CategoryID',
        ],
        'ThreadsAlpha' => [
            'type' => 'one-many',
            'class' => Thread::class,
            'local' => 'ID',
            'foreign' => 'CategoryID',
            'conditions' => [
                'order' => ['Title'=>'ASC']
            ]
        ],
    ];

    public static function getProtected($field)
    {
        return static::$$field;
    }
}
