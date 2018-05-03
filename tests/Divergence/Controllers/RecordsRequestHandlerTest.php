<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use ReflectionClass;

use PHPUnit\Framework\TestCase;

use Divergence\Tests\MockSite\Controllers\TagRequestHandler;

// for the test we assume that handleRequest() is invoked at the root path
// on a real site /tags/ but here just /

class RecordsRequestHandlerTest extends TestCase
{
    public function setUp()
    {
        App::$Config['environment'] = 'production';
    }

    public function testHandleRequest()
    {
        $_SERVER['REQUEST_URI'] = '/';

        $this->expectExceptionMessage('Invalid response mode');
        TagRequestHandler::handleRequest();
    }
}
