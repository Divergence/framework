<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Models\Versioning;
use Divergence\Tests\MockSite\Models\Canary;

class fakeCanary extends Canary
{ /* so we can test init on a brand new class */
    use Versioning;

    public static $nextErrorAsException = false;
    public static function handleError($query = null, $queryLog = null, $parameters = null)
    {
        if (static::$nextErrorAsException) {
            static::$nextErrorAsException = false;
            throw new \Exception('fakeCanary handlError exception');
        }
        return parent::handleError($query, $queryLog = null, $parameters = null);
    }
    public static function throwExceptionNextError()
    {
        static::$nextErrorAsException = true;
    }
}
