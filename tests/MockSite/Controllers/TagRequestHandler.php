<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests\MockSite\Controllers;

use Divergence\Tests\MockSite\Models\Tag;

use Divergence\Controllers\RecordsRequestHandler;

class TagRequestHandler extends RecordsRequestHandler
{
    public static $recordClass = Tag::class;

    public static function clear()
    {
        static::$pathStack = null;
        static::$_path = null;
        static::$responseMode = 'dwoo';
        static::$browseConditions = [];
        $_REQUEST = [];
    }
}
