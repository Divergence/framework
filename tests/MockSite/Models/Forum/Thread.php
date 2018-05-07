<?php
namespace Divergence\Tests\MockSite\Models\Forum;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Tests\MockSite\Mock\Data;

class Thread extends \Divergence\Models\Model
{
    use Versioning;
    use Relations;
    
    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'forum_threads';
    public static $singularNoun = 'thread';
    public static $pluralNoun = 'threads';
    
    // versioning
    public static $historyTable = 'forum_threads_history';
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    public static $fields = [
        'Title' => [
            'type' => 'string',
            'required' => true,
            'notnull' => true,
        ],
        'CategoryID' => [
            'type' => 'integer',
            'required' => true,
            'notnull' => true,
        ],
    ];

    public static $indexes = [];

    public static $relationships = [
        'Categories' => [
            'type' => 'one-many',
            'class' => Category::class,
            'local' => 'ID',
            'foreign' => 'ThreadID',
        ],
    ];
}
