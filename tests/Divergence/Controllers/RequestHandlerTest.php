<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests\Controllers;

use Divergence\App;
use ReflectionClass;

use PHPUnit\Framework\TestCase;

use Divergence\Controllers\RequestHandler;

class RequestHandlerTest extends TestCase
{
    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondEmpty()
    {
        $this->expectException('Dwoo\Exception');
        RequestHandler::respond('nothere');
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondJSON()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        RequestHandler::respond('nothere', json_decode($json, true), 'json');
        $this->expectOutputString($json);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondJSONP()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        RequestHandler::respond('nothere', json_decode($json, true), 'jsonp');
        $this->expectOutputString('var data = '.$json);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondDwoo()
    {
        $wabam = bin2hex(random_bytes(255));
        $tpl = realpath(__DIR__.'/../../../views').'/testDwoo.tpl';
        file_put_contents($tpl, $wabam);
        RequestHandler::respond('testDwoo.tpl');
        $this->expectOutputString($wabam);
        unlink($tpl);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondInjectableData()
    {
        $tpl = realpath(__DIR__.'/../../../views').'/testInjectableData.tpl';
        file_put_contents($tpl, '{dump $data}');
        RequestHandler::$injectableData = ['a'=>1,'b'=>2];
        RequestHandler::respond('testInjectableData.tpl', ['c'=>3]);
        unlink($tpl);
        $this->expectOutputString('<div style="background:#aaa; padding:5px; margin:5px; color:#000;">dump:<div style="padding-left:20px;">c = 3<br />a = 1<br />b = 2<br /></div></div>');
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondReturn()
    {
        $x = RequestHandler::respond('testDwoo.tpl', 'test', 'return');
        $this->assertEquals($x, ['TemplatePath'=>'testDwoo.tpl','data'=>['data'=>'test']]);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondInvalidResponseMode()
    {
        RequestHandler::$responseMode='fake';
        $this->expectException('Exception');
        RequestHandler::respond('we');
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::setPath
     */
    public function testSetPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';

        testableRequestHandler::clear();
        testableRequestHandler::testSetPath();
        
        $this->assertEquals([0=>'blogs',1=>'edit',2=>'1'], testableRequestHandler::$pathStack);
        $this->assertEquals([0=>'blogs',1=>'edit',2=>'1'], testableRequestHandler::testGetPath());

        testableRequestHandler::testSetPath('blogs');
        $this->assertEquals([0=>'blogs',1=>'edit',2=>'1'], testableRequestHandler::$pathStack);
        $this->assertEquals('blogs', testableRequestHandler::testGetPath());

        testableRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/';
        testableRequestHandler::testSetPath();
        $this->assertEquals([0=>''], testableRequestHandler::$pathStack);
        $this->assertEquals([0=>''], testableRequestHandler::testGetPath());
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::setOptions
     */
    public function testSetOptions()
    {
        $A = ['abc123'=>'xyz789','x'=>5];
        testableRequestHandler::testSetOptions($A);
        $this->assertEquals(testableRequestHandler::getOptions(), $A);
        $B = ['ohi'=>999,'x'=>6];
        testableRequestHandler::testSetOptions($B);
        $this->assertEquals(testableRequestHandler::getOptions(), array_merge($A, $B));
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::peekPath
     */
    public function testPeekPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';
        testableRequestHandler::clear();
        $this->assertEquals('blogs', testableRequestHandler::testPeekPath());
        testableRequestHandler::testShiftPath();
        $this->assertEquals('edit', testableRequestHandler::testPeekPath());
        testableRequestHandler::testShiftPath();
        $this->assertEquals('1', testableRequestHandler::testPeekPath());
        testableRequestHandler::testShiftPath();
        $this->assertEquals(false, testableRequestHandler::testPeekPath());
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::shiftPath
     */
    public function testShiftPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';
        testableRequestHandler::clear();
        $this->assertEquals('blogs', testableRequestHandler::testShiftPath());
        $this->assertEquals('edit', testableRequestHandler::testShiftPath());
        $this->assertEquals('1', testableRequestHandler::testShiftPath());
        $this->assertEquals(false, testableRequestHandler::testShiftPath());
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::getPath
     */
    public function testGetPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';
        testableRequestHandler::clear();
        $this->assertEquals(['blogs','edit','1'], testableRequestHandler::testGetPath());
    }
    /**
     * @covers Divergence\Controllers\RequestHandler::unshiftPath
     */
    public function testUnshiftPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';
        testableRequestHandler::clear();
        $this->assertEquals('blogs', testableRequestHandler::testShiftPath());
        $this->assertEquals('edit', testableRequestHandler::testShiftPath());
        $this->assertEquals('1', testableRequestHandler::testShiftPath());
        testableRequestHandler::testUnshiftPath('blogs');
        $this->assertEquals('blogs', testableRequestHandler::testShiftPath());

        
        testableRequestHandler::clear();
        testableRequestHandler::testUnshiftPath('json');
        $this->assertEquals('json', testableRequestHandler::testShiftPath());
    }
}

class testableRequestHandler extends RequestHandler
{
    public static function handleRequest()
    {
    }

    public static function testSetPath($path=null)
    {
        return static::setPath($path);
    }
    
    public static function testSetOptions($options)
    {
        return static::setOptions($options);
    }
    
    
    public static function testPeekPath()
    {
        return static::peekPath();
    }

    public static function testShiftPath()
    {
        return static::shiftPath();
    }

    public static function testGetPath()
    {
        return static::getPath();
    }
    
    public static function testUnshiftPath($string)
    {
        return static::unshiftPath($string);
    }

    public static function clear()
    {
        static::$pathStack = null;
        static::$_path = null;
    }

    public static function getOptions()
    {
        return static::$_options;
    }
}
