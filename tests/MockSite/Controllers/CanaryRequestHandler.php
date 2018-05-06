<?php
namespace Divergence\Tests\MockSite\Controllers;

use Divergence\Tests\MockSite\Models\Canary;

use Divergence\Controllers\RecordsRequestHandler;

class CanaryRequestHandler extends RecordsRequestHandler
{
    public static $recordClass = Canary::class;

    public static function clear()
    {
        static::$pathStack = null;
        static::$_path = null;
        static::$responseMode = 'dwoo';
        static::$browseConditions = [];
        $_REQUEST = [];
    }
}
