<?php
namespace Divergence\Tests\Controllers;

use Divergence\App;
use ReflectionClass;
use Divergence\Helpers\JSON;
use org\bovigo\vfs\vfsStream;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStreamWrapper;


use Divergence\IO\Database\MySQL as DB;

use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Controllers\TagRequestHandler;
use Divergence\Tests\MockSite\Controllers\CanaryRequestHandler;
use Divergence\Tests\MockSite\Controllers\SecureCanaryRequestHandler;
use Divergence\Tests\MockSite\Controllers\ParanoidCanaryRequestHandler;

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

    public function testGetByHandleWithNoHandleFieldOnObject()
    {
        TagRequestHandler::$recordClass = \StdObject::class;
        $this->assertNull(TagRequestHandler::getRecordByHandle('nada'));
        TagRequestHandler::$recordClass = Tag::class;
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

    public function testHandleRequestOneValidRecordUnhandled()
    {
        $expected = [
            'success'=>false,
            'failed' => [
                'errors' => 'Malformed request.',
            ],
        ];
        $expected = json_encode($expected);

        TagRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/1/notvalid';
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
        $expected = $Canary->data;
        $expected['Created'] = $x['data']['Created'];
        $this->assertTrue($x['success']);
        $this->assertArraySubset($expected, $x['data']); // delete should return the record
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // delete
    // as a method GET not supposed to do anything other than throw a confirm dialog style message
    public function testDeleteGET()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/delete';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals('Are you sure you want to delete this '.Canary::$singularNoun.'?', $x['question']);
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
        JSON::$inputStream = 'php://input';
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
        JSON::$inputStream = 'php://input';
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



    public function testMultiSaveRequestWithOneSave()
    {
        $MockData = Canary::avis();
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_REQUEST = ['data'=>$MockData];
        $_SERVER['REQUEST_URI'] = '/json/save';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData, $x['data'][0]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testMultiSaveRequestWithThreeNew()
    {
        $x = 0;
        while ($x < 3) {
            $MockData[$x] = Canary::avis();
            $MockData[$x]['DateOfBirth'] = date('Y-m-d', $MockData[$x]['DateOfBirth']);
            if (is_integer($MockData[$x]['Colors'])) {
                $MockData[$x]['Colors'] = [$MockData[$x]['Colors']];
            }
            $x++;
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_REQUEST = ['data'=>$MockData];
        $_SERVER['REQUEST_URI'] = '/json/save';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData[0], $x['data'][0]);
        $this->assertArraySubset($MockData[1], $x['data'][1]);
        $this->assertArraySubset($MockData[2], $x['data'][2]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testMultiSaveRequestWithBadData()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_REQUEST = ['nothing'=>'whatever','someweirdstuff'];
        $_SERVER['REQUEST_URI'] = '/json/save';
        ob_start();
        CanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Save expects "data" field as array of records.', $x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testMultiSaveRequestWithThreeNewAsJSON()
    {
        $x = 0;
        while ($x < 3) {
            $MockData[$x] = Canary::avis();
            $MockData[$x]['DateOfBirth'] = date('Y-m-d', $MockData[$x]['DateOfBirth']);
            if (is_integer($MockData[$x]['Colors'])) {
                $MockData[$x]['Colors'] = [$MockData[$x]['Colors']];
            }
            $x++;
        }
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        CanaryRequestHandler::clear();
        $_PUT = ['data'=>$MockData];
        $_SERVER['REQUEST_URI'] = '/json/save';
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        CanaryRequestHandler::handleRequest();
        JSON::$inputStream = 'php://input';
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData[0], $x['data'][0]);
        $this->assertArraySubset($MockData[1], $x['data'][1]);
        $this->assertArraySubset($MockData[2], $x['data'][2]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testMultiSaveRequestWithTwoEditsOneNotFound()
    {
        $Existing = Canary::getAll([
            'order' => [
                'ID' => 'DESC',
            ],
            'limit' => 3,
        ]);

        $Existing[0]->setFields(Canary::avis());
        $Existing[2]->setFields(Canary::avis());

        $MockData = [
            $Existing[0]->data,
            $Existing[1]->data,
            $Existing[2]->data,
        ];

        $Existing[1]->destroy();
        
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        CanaryRequestHandler::clear();
        $_PUT = ['data'=>$MockData];
        $_SERVER['REQUEST_URI'] = '/json/save';
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        CanaryRequestHandler::handleRequest();
        JSON::$inputStream = 'php://input';
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);

        // ignore created field cause sometimes save take second or two and the values change
        $MockData[0]['Created'] = $x['data'][0]['Created'];
        $MockData[2]['Created'] = $x['data'][1]['Created'];
        $MockData[1]['Created'] = $x['failed'][0]['record']['Created'];


        $this->assertArraySubset($MockData[0], $x['data'][0]);
        $this->assertArraySubset($MockData[2], $x['data'][1]);
        $this->assertArraySubset($MockData[1], $x['failed'][0]['record']);
        $this->assertEquals('Record not found', $x['failed'][0]['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testHandleMultiDestroyRequestByPUT()
    {
        $Existing = Canary::getAll([
            'order' => [
                'ID' => 'DESC',
            ],
            'limit' => 3,
        ]);

        $expect = [$Existing[0]->data,$Existing[2]->data];

        $MockData = [
            $Existing[0]->ID,
            $Existing[1]->ID,
            $Existing[2]->ID,
        ];

        $Existing[1]->destroy();
        
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        CanaryRequestHandler::clear();
        $_PUT = ['data'=>$MockData];
        $_SERVER['REQUEST_URI'] = '/json/destroy';
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        CanaryRequestHandler::handleRequest();
        JSON::$inputStream = 'php://input';
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
    


        $expect[0]['Created'] = $x['data'][0]['Created'];
        $expect[1]['Created'] = $x['data'][1]['Created'];

        $this->assertArraySubset($expect[0], $x['data'][0]);
        $this->assertArraySubset($expect[1], $x['data'][1]);
        $this->assertEquals($MockData[1], $x['failed'][0]['record']);
        $this->assertEquals('ID not found', $x['failed'][0]['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testHandleMultiDestroyRequestByDELETE()
    {
        $Existing = Canary::getAll([
            'order' => [
                'ID' => 'DESC',
            ],
            'limit' => 3,
        ]);

        $expect = [$Existing[0]->data,$Existing[2]->data];

        $MockData = [
            ['ID' => $Existing[0]->ID],
            ['ID' => $Existing[1]->ID],
            ['ID' => $Existing[2]->ID],
        ];

        $Existing[1]->destroy();
        
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        CanaryRequestHandler::clear();
        $_PUT = ['data'=>$MockData];
        $_SERVER['REQUEST_URI'] = '/json/destroy';
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        CanaryRequestHandler::handleRequest();
        JSON::$inputStream = 'php://input';
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
    


        $expect[0]['Created'] = $x['data'][0]['Created'];
        $expect[1]['Created'] = $x['data'][1]['Created'];

        $this->assertArraySubset($expect[0], $x['data'][0]);
        $this->assertArraySubset($expect[1], $x['data'][1]);
        $this->assertEquals($MockData[1], $x['failed'][0]['record']);
        $this->assertEquals('ID not found', $x['failed'][0]['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testHandleMultiDestroyRequestWithError()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        CanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/destroy';
        ob_start();
        CanaryRequestHandler::handleRequest();
        JSON::$inputStream = 'php://input';
        $x = json_decode(ob_get_clean(), true);
        
        $this->assertEquals(['success'=>false,'failed'=>['errors'=>'Save expects "data" field as array of records.']],$x);
        
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testThrowUnauthorizedError()
    {
        ob_start();
        CanaryRequestHandler::throwUnauthorizedError();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals(['success'=>false,'failed'=>['errors'=>'Login required.']], $x);
    }

    public function testGetTemplate()
    {
        $this->assertEquals('someString', CanaryRequestHandler::getTemplateName('some string'));
        $this->assertEquals('somestring', CanaryRequestHandler::getTemplateName('somestring'));
    }

    public function testApplyRecordDelta()
    {
        CanaryRequestHandler::$editableFields = ['Name'];
        $data = Canary::avis();
        $Canary = new Canary();
        CanaryRequestHandler::applyRecordDelta($Canary, $data);
        $this->assertEquals($data['Name'], $Canary->Name);
        $this->assertNotEquals($data['Handle'], $Canary->Handle);
        CanaryRequestHandler::$editableFields = null;
    }


    public function testAccessDeniedCreate()
    {
        // create
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        SecureCanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/create';
        ob_start();
        SecureCanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.',$x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testAccessDeniedEdit()
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
        SecureCanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/edit';
        ob_start();
        SecureCanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.',$x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    
    // delete
    public function testAccessDeniedDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        SecureCanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/delete';
        ob_start();
        SecureCanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.',$x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testAPIAccessDeniedCreate()
    {
        // create
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ParanoidCanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/create';
        ob_start();
        ParanoidCanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('API access required.',$x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testAPIAccessDeniedEdit()
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
        ParanoidCanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/edit';
        ob_start();
        ParanoidCanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('API access required.',$x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    
    // delete
    public function testAPIAccessDeniedDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ParanoidCanaryRequestHandler::clear();
        $_SERVER['REQUEST_URI'] = '/json/'.$ID.'/delete';
        ob_start();
        ParanoidCanaryRequestHandler::handleRequest();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('API access required.',$x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
}
