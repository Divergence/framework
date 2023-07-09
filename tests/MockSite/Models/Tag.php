<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    protected $Tag;

    protected $Slug;

    /* expose protected attributes for unit testing */
    public static function getProtected($field)
    {
        return static::$$field;
    }

    public static function getRecordClass($record)
    {
        return static::_getRecordClass($record);
    }
}
