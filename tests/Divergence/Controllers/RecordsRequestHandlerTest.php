<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use ReflectionClass;

use PHPUnit\Framework\TestCase;

use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Controllers\TagRequestHandler;
use Divergence\Tests\MockSite\Controllers\CanaryRequestHandler;

/*
 * About Unit Testing Divergence Controllers
 * 
 *   - These unit tests will attempt to simulate real HTTP requests.
 *   - That means setting $_POST, and $_SERVER['REQUEST_URI']
 *   - If it's a regular web call and not a helper function it should still be
 *     invoked with ControllerClassName::handleRequest()
 *   - All the tests should assume the Controller that is being used lives at /
 *     ie: $_SERVER['REQUEST_URI'] = '/'
 *   - When you want to test the "create" page simply set
 *      $_SERVER['REQUEST_URI'] = '/create'
 *          It will assume a $_GET request and attempt to show you the
 *          create template for that model.
 *   - When you're ready to test submitting data to the create page simply set
 *      $_SERVER['REQUEST_METHOD'] = 'POST';
 *      $_POST =  [ /.. record data ../];
 *      Create/Update also support PUT where the input data is a JSON string.
 */

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

    public function testHandleRequestOneValidRecordByHandleWithNoHandleField()
    {
        $expected = [
            'success'=>false,
            'failed'=>[
                'errors'=> 'Record not found.'
            ]
        ];
        $expected = json_encode($expected);

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/Linux';
        TagRequestHandler::handleRequest();
        $this->expectOutputString($expected);
    }

    public function testHandleRequestNoRecordFound()
    {
        $expected = [
            'success'=>false,
            'failed'=>[
                'errors'=> 'Record not found.'
            ]
        ];
        $expected = json_encode($expected);

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/999';
        TagRequestHandler::handleRequest();
        $this->expectOutputString($expected);
    }

    public function testCreateEditDeleteRecord()
    {
        // create
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d',$MockData['DateOfBirth']);
        if(is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/create';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(),true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData,$x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // edit
        $ID = $x['data']['ID'];
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d',$MockData['DateOfBirth']);
        if(is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/edit';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(),true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData,$x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // delete
        $_POST['ID'] = $ID;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/delete';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(),true);
        $this->assertTrue($x['success']);
        unset($MockData['EyeColors']);
        unset($MockData['Colors']);
        $this->assertArraySubset($MockData,$x['data']); // delete should return the record
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}
