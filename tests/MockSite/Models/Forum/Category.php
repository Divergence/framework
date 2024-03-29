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
use Divergence\Models\Mapping\Relation;

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

    public static $indexes = [];

    protected string $Name;

    #[Relation(
        type:'one-many',
        class:Thread::class,
        local: 'ID',
        foreign: 'CategoryID'
    )]
    protected ?array $Threads;

    #[Relation(
        type:'one-many',
        class:Thread::class,
        local: 'ID',
        foreign: 'CategoryID',
        conditions: [
            'Created > DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        ],
        order: ['Title'=>'ASC']
    )]
    protected ?array $ThreadsAlpha;

    public static function getProtected($field)
    {
        return static::$$field;
    }
}
