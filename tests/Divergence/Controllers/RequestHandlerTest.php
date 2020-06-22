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

use Twig\Error\LoaderError;

use PHPUnit\Framework\TestCase;
use Divergence\Responders\Emitter;
use Divergence\Responders\Response;
use Divergence\Responders\JsonBuilder;
use Divergence\Responders\TwigBuilder;
use Divergence\Responders\JsonpBuilder;
use Psr\Http\Message\ResponseInterface;
use Divergence\Controllers\RequestHandler;
use Psr\Http\Message\ServerRequestInterface;

class RequestHandlerTest extends TestCase
{
    /**
     *
     */
    public function testRespondEmpty()
    {
        $this->expectException(LoaderError::class);
        $controller = new testableRequestHandler();
        $controller->respond('nohere');
    }

    /**
     *
     */
    public function testRespondJSON()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        $controller = new testableRequestHandler();
        $controller->responseBuilder = JsonBuilder::class;
        $response = $controller->respond('nothere', json_decode($json, true), 'json');
        (new Emitter($response))->emit();
        $this->expectOutputString($json);
    }

    /**
     *
     */
    public function testRespondJSONP()
    {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        $controller = new testableRequestHandler();
        $controller->responseBuilder = JsonpBuilder::class;
        $response = $controller->respond('nothere', json_decode($json, true), 'jsonp');
        (new Emitter($response))->emit();
        $this->expectOutputString('var data = '.$json);
    }

    /**
     *
     */
    public function testRespondTwig()
    {
        $wabam = bin2hex(random_bytes(255));
        $tpl = realpath(__DIR__.'/../../../views').'/testTwig.twig';
        file_put_contents($tpl, $wabam);
        $controller = new testableRequestHandler();
        $response = $controller->respond('testTwig.twig');
        (new Emitter($response))->emit();
        $this->expectOutputString($wabam);
        unlink($tpl);
    }

    /**
     *
     */
    /*public function testRespondInjectableData()
    {
        $tpl = realpath(__DIR__.'/../../../views').'/testInjectableData.twig';
        file_put_contents($tpl, '{dump $data}');
        RequestHandler::$injectableData = ['a'=>1,'b'=>2];
        RequestHandler::respond('testInjectableData.tpl', ['c'=>3]);
        unlink($tpl);
        $this->expectOutputString('<div style="background:#aaa; padding:5px; margin:5px; color:#000;">dump:<div style="padding-left:20px;">c = 3<br />a = 1<br />b = 2<br /></div></div>');
    }*/

    /**
     *
     */
    /*public function testRespondReturn()
    {
        $controller = new testableRequestHandler();
        $controller->respond('testTwig.twig');
        $x = RequestHandler::respond('testDwoo.twig', 'test', 'return');
        $this->assertEquals($x, ['TemplatePath'=>'testDwoo.tpl','data'=>['data'=>'test']]);
    }*/

    /**
     *
     */
    /*public function testRespondInvalidResponseMode()
    {
        RequestHandler::$responseMode='fake';
        $this->expectException('Exception');
        RequestHandler::respond('we');
    }*/

    /**
     *
     */
    /*public function testSetPath()
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
    }*/

    /**
     *
     */
    /*public function testSetOptions()
    {
        $A = ['abc123'=>'xyz789','x'=>5];
        testableRequestHandler::testSetOptions($A);
        $this->assertEquals(testableRequestHandler::getOptions(), $A);
        $B = ['ohi'=>999,'x'=>6];
        testableRequestHandler::testSetOptions($B);
        $this->assertEquals(testableRequestHandler::getOptions(), array_merge($A, $B));
    }*/

    /**
     *
     */
    /*public function testPeekPath()
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
    }*/

    /**
     *
     */
    /*public function testShiftPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';
        testableRequestHandler::clear();
        $this->assertEquals('blogs', testableRequestHandler::testShiftPath());
        $this->assertEquals('edit', testableRequestHandler::testShiftPath());
        $this->assertEquals('1', testableRequestHandler::testShiftPath());
        $this->assertEquals(false, testableRequestHandler::testShiftPath());
    }*/

    /**
     *
     */
    /*public function testGetPath()
    {
        $_SERVER['REQUEST_URI'] = '/blogs/edit/1';
        testableRequestHandler::clear();
        $this->assertEquals(['blogs','edit','1'], testableRequestHandler::testGetPath());
    }*/
    /**
     *
     */
    /*public function testUnshiftPath()
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
    }*/
}

class testableRequestHandler extends RequestHandler
{
    public string $responseBuilder = TwigBuilder::class;
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $builder = $this->responseBuilder;
        return new Response(new $builder());
    }

    public function testSetPath($path=null)
    {
        return $this->setPath($path);
    }

    public function testSetOptions($options)
    {
        return $this->setOptions($options);
    }


    public function testPeekPath()
    {
        return $this->peekPath();
    }

    public function testShiftPath()
    {
        return $this->shiftPath();
    }

    public function testUnshiftPath($string)
    {
        return $this->unshiftPath($string);
    }
}
