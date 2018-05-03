<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use ReflectionClass;

use PHPUnit\Framework\TestCase;

use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Controllers\TagRequestHandler;
use Divergence\Tests\MockSite\Controllers\CanaryRequestHandler;

// for the test we assume that handleRequest() is invoked at the root path
// on a real site /tags/ but here just /

class RecordsRequestHandlerTest extends TestCase
{
    public function setUp()
    {
        App::$Config['environment'] = 'production';
    }

    public function testHandleRequestJSON()
    {
        $expected = [
            // order matters because we are comparing json in string form
            'success'=>true,
            'data'=>[],
            'conditions'=>[],
            'total'=>0,
            'limit'=>false,
            'offset'=>false
        ];
        $Records = Tag::getAll();
        foreach($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        $expected['total'] = count($Records)."";
        $expected = json_encode($expected);
        
        
        $this->expectOutputString($expected);

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        TagRequestHandler::handleRequest();
    }

    public function testHandleRequest()
    {
        $this->expectException('Dwoo\\Exception');
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/';
        TagRequestHandler::handleRequest();
    }

    public function testHandleRequestOneValidRecord()
    {
        $expected = [
            'success'=>true,
            'data'=> Tag::getByID(1)->data,
        ];
        $expected = json_encode($expected);

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/1';
        TagRequestHandler::handleRequest();
        $this->expectOutputString($expected);
    }

    public function testHandleRequestOneValidRecordByHandle()
    {
        $Object = Canary::getByID(1);
        $expected = [
            'success'=>true,
            'data'=> $Object->data,
        ];
        $expected = json_encode($expected);

        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$Object->Handle;
        CanaryRequestHandler::handleRequest();
        $this->expectOutputString($expected);
    }
}
