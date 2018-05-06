<?php
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
