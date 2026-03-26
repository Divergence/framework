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
    // ActiveRecord configuration
    public static $tableName = 'tags';

    private $Tag;
    private $Slug;

    public function getSlugPath(): string
    {
        return '/' . $this->Slug . '/';
    }

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
