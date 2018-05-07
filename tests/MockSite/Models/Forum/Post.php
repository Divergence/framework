<?php
namespace Divergence\Tests\MockSite\Models\Forum;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Tests\MockSite\Mock\Data;

class Post extends \Divergence\Models\Model
{
    use Versioning;
    use Relations;
    
    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'forum_posts';
    public static $singularNoun = 'post';
    public static $pluralNoun = 'posts';
    
    // versioning
    public static $historyTable = 'forum_posts_history';
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    public static $fields = [
        'Content' => [
            'type' => 'clob',
            'required' => true,
            'notnull' => true,
        ],
        'ThreadID' => [
            'type' => 'integer',
            'required' => true,
            'notnull' => true,
        ],
    ];

    public static $indexes = [
        'ThreadID' => [
            'fields' => [
                'ThreadID',
            ],
            'unique' => false,
        ],
    ];

    public static $relationships = [
        'Thread' => [
            'type' => 'one-one',
            'class' => Thread::class,
            'local' => 'ThreadID',
            'foreign' => 'ID',
        ],
    ];
}
