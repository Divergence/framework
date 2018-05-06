<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use ReflectionClass;
use PHPUnit\Framework\TestCase;
use Divergence\Helpers\JSON;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;


use Divergence\IO\Database\MySQL as DB;

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
            'offset'=>false,
        ];
        $Records = Tag::getAll();
        foreach ($Records as $Record) {
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
                'errors'=> 'Record not found.',
            ],
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
                'errors'=> 'Record not found.',
            ],
        ];
        $expected = json_encode($expected);

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/999';
        TagRequestHandler::handleRequest();
        $this->expectOutputString($expected);
    }

    public function testCreate()
    {
        // create
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/create';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData, $x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testEdit()
    {
        // edit
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/edit';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData, $x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    
    // delete
    public function testDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/delete';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($Canary->data, $x['data']); // delete should return the record
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testCreateFromJSON()
    {
        // create
        $MockData = Canary::avis();
        $PUT = ['data'=>$MockData];
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/create';
        vfsStream::setup('input', null, ['data' => json_encode($PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData, $x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    
    public function testEditFromJSON()
    {
        // edit
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $MockData = Canary::avis();
        $PUT = ['data'=>$MockData];
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/edit';
        vfsStream::setup('input', null, ['data' => json_encode($PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData, $x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testHandleBrowseRequestSorted()
    {
        $expected = [
            'success'=>true,
            'data'=>[],
            'conditions'=>[],
            'total'=>0,
            'limit'=>false,
            'offset'=>false,
        ];
        $Records = Tag::getAll(['order'=>'Tag DESC']);
        foreach ($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        $expected['total'] = count($Records)."";

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['sort'] = json_encode([
            [
                'property' => 'Tag',
                'direction' => 'DESC',
            ],
        ]);
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestFiltered()
    {
        $expected = [
            'success'=>true,
            'data'=>[],
            'conditions'=>[
                'Tag'=>'Linux',
            ],
            'total'=>0,
            'limit'=>false,
            'offset'=>false,
        ];
        $Records = Tag::getAllByField('Tag', 'Linux');
        foreach ($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        $expected['total'] = count($Records)."";

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['filter'] = json_encode([
            [
                'property' => 'Tag',
                'value' => 'Linux',
            ],
        ]);
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestPagination()
    {
        $expected = [
            'success'=>true,
            'data'=>[],
            'conditions'=>[],
            'total'=>0,
            'limit'=>2,
            'offset'=>4,
        ];
        $Records = Tag::getAll(['limit'=>$expected['limit'],'offset'=>$expected['offset'],'calcFoundRows'=>true]);
        $expected['total'] = DB::foundRows();
        foreach ($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['limit'] = $expected['limit'];
        $_REQUEST['offset'] = $expected['offset'];
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestPaginationWithStart()
    {
        $expected = [
            'success'=>true,
            'data'=>[],
            'conditions'=>[],
            'total'=>0,
            'limit'=>2,
            'offset'=>4,
        ];
        $Records = Tag::getAll(['limit'=>$expected['limit'],'offset'=>$expected['offset'],'calcFoundRows'=>true]);
        $expected['total'] = DB::foundRows();
        foreach ($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['limit'] = $expected['limit'];
        $_REQUEST['start'] = $expected['offset'];
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestBuiltInConditions()
    {
        $expected = [
            'success'=>true,
            'data'=>[],
            'conditions'=>[
                "Tag NOT IN ('Linux','OSX')",
            ],
            'total'=>0,
            'limit'=>false,
            'offset'=>false,
        ];
        $Records = Tag::getAllByWhere($expected['conditions'], ['calcFoundRows'=>true]);
        $expected['total'] = DB::foundRows();
        foreach ($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        TagRequestHandler::$browseConditions = $expected['conditions'];
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestBuiltInConditionsAsString()
    {
        $expected = [
            'success'=>true,
            'data'=>[],
            'conditions'=>[
                "Tag NOT IN ('Linux','OSX')",
            ],
            'total'=>0,
            'limit'=>false,
            'offset'=>false,
        ];
        $Records = Tag::getAllByWhere($expected['conditions'], ['calcFoundRows'=>true]);
        $expected['total'] = DB::foundRows();
        foreach ($Records as $Record) {
            $expected['data'][] = $Record->data;
        }
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        TagRequestHandler::$browseConditions = $expected['conditions'][0];
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestInvalidFilter()
    {
        $expected = [
            'success' => false
            ,'failed' => [
                'errors'	=>	'Invalid filter.',
            ],
        ];
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['filter'] = 'fail';
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestInvalidSorter()
    {
        $expected = [
            'success' => false
            ,'failed' => [
                'errors'	=>	'Invalid sorter.',
            ],
        ];
        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['sort'] = 'fail';
        ob_start();
        TagRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }
}
