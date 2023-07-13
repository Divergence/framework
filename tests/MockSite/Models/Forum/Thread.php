<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite\Models\Forum;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Models\Mapping\Column;
use Divergence\Models\Mapping\Relation;

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

    public static $indexes = [];

    #[Column(type: "string", required:true, notnull: true)]
    protected $Title;

    #[Column(type: "integer", required:true, notnull: true)]
    protected $CategoryID;

    #[Relation(
        type:'one-many',
        class:Category::class,
        local: 'ID',
        foreign: 'ThreadID',
    )]
    protected $Categories;
}
