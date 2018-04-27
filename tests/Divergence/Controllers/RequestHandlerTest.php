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

}