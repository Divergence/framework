<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use Divergence\Controllers\RequestHandler;

use PHPUnit\Framework\TestCase;

class RequestHandlerTest extends TestCase
{
    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondEmpty() {
        $this->expectException('Dwoo\Exception');
        RequestHandler::respond('nothere');
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondJSON() {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        RequestHandler::respond('nothere',json_decode($json,true),'json');
        $this->expectOutputString($json);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testRespondJSONP() {
        $json = '{"array":[1,2,3],"boolean":true,"null":null,"number":123,"object":{"a":"b","c":"d","e":"f"},"string":"Hello World"}';
        RequestHandler::respond('nothere',json_decode($json,true),'jsonp');
        $this->expectOutputString('var data = '.$json);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testDwoo() {
        $wabam = bin2hex(random_bytes(255));
        $tpl = realpath(__DIR__.'/../../../views').'/testDwoo.tpl';
        file_put_contents($tpl,$wabam);
        RequestHandler::respond('testDwoo.tpl');
        $this->expectOutputString($wabam);
        unlink($tpl);
    }

    /**
     * @covers Divergence\Controllers\RequestHandler::respond
     */
    public function testInjectableData() {
        $tpl = realpath(__DIR__.'/../../../views').'/testInjectableData.tpl';
        file_put_contents($tpl,'{dump $data}');
        RequestHandler::$injectableData = ['a'=>1,'b'=>2];
        RequestHandler::respond('testInjectableData.tpl',['c'=>3]);
        unlink($tpl);
        $this->expectOutputString('<div style="background:#aaa; padding:5px; margin:5px; color:#000;">dump:<div style="padding-left:20px;">c = 3<br />a = 1<br />b = 2<br /></div></div>');
    }
}