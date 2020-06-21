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
use Divergence\Helpers\JSON;
use Divergence\Routing\Path;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Divergence\Responders\Emitter;
use GuzzleHttp\Psr7\ServerRequest;
use org\bovigo\vfs\vfsStreamWrapper;
use Divergence\Responders\JsonBuilder;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Controllers\RequestHandler;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\Models\Testables\fakeCanary;
use Divergence\Tests\Models\Testables\relationalCanary;
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

        //$this->App->Config['environment'] = 'production';
    }

    public function tearDown()
    {
        if (in_array($this->getName(), ['testProcessDatumDestroyFailed','testEditWithError'])) {
            DB::nonQuery('UNLOCK TABLES');
        }
    }

    public function emit($controller, $path)
    {
        $ctrl = is_a($controller, RequestHandler::class) ? $controller : new $controller();


        $_SERVER['REQUEST_URI'] = $path;
        App::$App->Path = new Path($path);
        $response = $ctrl->handle(ServerRequest::fromGlobals());
        (new Emitter($response))->emit();
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


        $this->emit(TagRequestHandler::class, '/json/');
    }

    public function testGetByHandleWithNoHandleFieldOnObject()
    {
        TagRequestHandler::$recordClass = \StdObject::class;
        $controller = new TagRequestHandler();
        $this->assertNull($controller->getRecordByHandle('nada'));
        TagRequestHandler::$recordClass = Tag::class;
    }

    public function testHandleRequest()
    {
        $this->expectException(LoaderError::class);
        $this->emit(TagRequestHandler::class, '/');
    }

    public function testHandleRequestOneValidRecord()
    {
        $expected = [
            'success'=>true,
            'data'=> Tag::getByID(1)->data,
        ];
        $expected = json_encode($expected);

        $this->expectOutputString($expected);

        $this->emit(TagRequestHandler::class, '/json/1');
    }

    public function testHandleRequestOneValidRecordByHandle()
    {
        $Object = Canary::getByID(1);
        $expected = [
            'success'=>true,
            'data'=> $Object->data,
        ];
        $expected = json_encode($expected);

        $this->expectOutputString($expected);
        $this->emit(CanaryRequestHandler::class, '/json/'.$Object->Handle);
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

        $this->emit(TagRequestHandler::class, '/json/Linux');
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

        $this->emit(TagRequestHandler::class, '/json/999');
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


        $this->emit(TagRequestHandler::class, '/json/1/notvalid');
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
        $_SERVER['REQUEST_URI'] = '/json/create';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/create');
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
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/'.$ID.'/edit');
        $x = json_decode(ob_get_clean(), true);
        $this->assertTrue($x['success']);
        $this->assertArraySubset($MockData, $x['data']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testEditWithError()
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
        ob_start();
        DB::nonQuery('LOCK TABLES `canaries` READ');
        $this->emit(CanaryRequestHandler::class, '/json/'.$ID.'/edit');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Database error!', $x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testEditAsGet()
    {
        // edit
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $MockData = Canary::avis();
        $_POST = $MockData;
        $MockData['DateOfBirth'] = date('Y-m-d', $MockData['DateOfBirth']);
        if (is_integer($MockData['Colors'])) {
            $MockData['Colors'] = [$MockData['Colors']];
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/'.$ID.'/edit';
        $this->expectException(\Exception::class);
        $this->emit(CanaryRequestHandler::class, '/'.$ID.'/edit');
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // delete
    public function testDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/'.$ID.'/delete');
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
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/'.$ID.'/delete');
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
        $_SERVER['REQUEST_URI'] = '/json/create';
        vfsStream::setup('input', null, ['data' => json_encode($PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/create');
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
        vfsStream::setup('input', null, ['data' => json_encode($PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/'.$ID.'/edit');
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

        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['sort'] = json_encode([
            [
                'property' => 'Tag',
                'direction' => 'DESC',
            ],
        ]);
        ob_start();
        $this->emit(TagRequestHandler::class, '/json/');
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

        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST['filter'] = json_encode([
            [
                'property' => 'Tag',
                'value' => 'Linux',
            ],
        ]);
        ob_start();
        $this->emit(TagRequestHandler::class, '/json/');
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
        $_REQUEST = [];
        $_REQUEST['limit'] = $expected['limit'];
        $_REQUEST['offset'] = $expected['offset'];
        ob_start();
        $this->emit(TagRequestHandler::class, '/json/');
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
        $_SERVER['REQUEST_URI'] = '/json/';
        $_REQUEST = [];
        $_REQUEST['limit'] = $expected['limit'];
        $_REQUEST['start'] = $expected['offset'];
        ob_start();
        $this->emit(TagRequestHandler::class, '/json/');
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
        $controller = new TagRequestHandler();
        $controller->browseConditions = $expected['conditions'];
        ob_start();
        $this->emit($controller, '/json/');
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
        $controller = new TagRequestHandler();
        $controller->browseConditions = $expected['conditions'][0];
        ob_start();
        $this->emit($controller, '/json/');
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestInvalidFilter()
    {
        $expected = [
            'success' => false,
            'failed' => [
                'errors'	=>	'Invalid filter.',
            ],
        ];
        $_REQUEST['filter'] = 'fail';
        ob_start();
        $this->emit(TagRequestHandler::class, '/json/');
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals($expected, $x);
    }

    public function testHandleBrowseRequestInvalidSorter()
    {
        $expected = [
            'success' => false,
            'failed' => [
                'errors'	=>	'Invalid sorter.',
            ],
        ];
        $_REQUEST['sort'] = 'fail';
        ob_start();
        $this->emit(TagRequestHandler::class, '/json/');
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
        $_REQUEST = ['data'=>$MockData];
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/save');
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
        $_REQUEST = ['data'=>$MockData];
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/save');
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
        $_REQUEST = ['nothing'=>'whatever','someweirdstuff'];
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/save');
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
        $_PUT = ['data'=>$MockData];
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/save');
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
        $_PUT = ['data'=>$MockData];
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/save');
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
        $_PUT = ['data'=>$MockData];
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/destroy');
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
        $_PUT = ['data'=>$MockData];
        vfsStream::setup('input', null, ['data' => json_encode($_PUT)]);
        JSON::$inputStream = 'vfs://input/data';
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/destroy');
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
        ob_start();
        $this->emit(CanaryRequestHandler::class, '/json/destroy');
        JSON::$inputStream = 'php://input';
        $x = json_decode(ob_get_clean(), true);

        $this->assertEquals(['success'=>false,'failed'=>['errors'=>'Save expects "data" field as array of records.']], $x);

        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testThrowUnauthorizedError()
    {
        ob_start();
        $controller = new CanaryRequestHandler();
        $controller->responseBuilder = JsonBuilder::class;
        $response = $controller->throwUnauthorizedError();
        (new Emitter($response))->emit();
        $x = json_decode(ob_get_clean(), true);
        $this->assertEquals(['success'=>false,'failed'=>['errors'=>'Login required.']], $x);
    }

    public function testGetTemplate()
    {
        $controller = new CanaryRequestHandler();
        $this->assertEquals('someString', $controller->getTemplateName('some string'));
        $this->assertEquals('somestring', $controller->getTemplateName('somestring'));
    }

    public function testApplyRecordDelta()
    {
        $controller = new CanaryRequestHandler();

        $controller->editableFields = ['Name'];
        $data = Canary::avis();
        $Canary = new Canary();
        $controller->applyRecordDelta($Canary, $data);
        $this->assertEquals($data['Name'], $Canary->Name);
        $this->assertNotEquals($data['Handle'], $Canary->Handle);
        $controller->editableFields = null;
    }

    public function testAccessDeniedBrowse()
    {
        // create
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $this->emit(SecureCanaryRequestHandler::class, '/json');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.', $x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
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
        ob_start();
        $this->emit(SecureCanaryRequestHandler::class, '/json/create');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.', $x['failed']['errors']);
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
        ob_start();
        $this->emit(SecureCanaryRequestHandler::class, '/json/'.$ID.'/edit');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.', $x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // delete
    public function testAccessDeniedDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        $this->emit(SecureCanaryRequestHandler::class, '/json/'.$ID.'/delete');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.', $x['failed']['errors']);
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
        ob_start();
        $this->emit(ParanoidCanaryRequestHandler::class, '/json/create');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('API access required.', $x['failed']['errors']);
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
        ob_start();
        $this->emit(ParanoidCanaryRequestHandler::class, '/json/'.$ID.'/edit');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('API access required.', $x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // delete
    public function testAPIAccessDeniedDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        $this->emit(ParanoidCanaryRequestHandler::class, '/json/'.$ID.'/delete');
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('API access required.', $x['failed']['errors']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // delete
    public function testNoWriteAccessDelete()
    {
        $ID = DB::oneValue('SELECT ID FROM `canaries` ORDER BY ID DESC');
        $Canary = Canary::getByID($ID);
        ob_start();
        $controller = new SecureCanaryRequestHandler();
        $controller->responseBuilder = JsonBuilder::class;
        $response = $controller->handleDeleteRequest($Canary);
        (new Emitter($response))->emit();
        $x = json_decode(ob_get_clean(), true);
        $this->assertFalse($x['success']);
        $this->assertEquals('Login required.', $x['failed']['errors']);
    }

    // write access denied
    public function testProcessDatumSaveNoWriteAccess()
    {
        $this->expectException('Exception');
        $controller = new SecureCanaryRequestHandler();
        $controller->processDatumSave([
            'ID' => '1',
            'Name' => 'whatever',
        ]);
    }

    // database error
    public function testProcessDatumSaveDatabaseError()
    {
        $this->expectException('Exception');
        $controller = new CanaryRequestHandler();
        $controller->processDatumSave([
            'Created' => 'fake',
        ]);
    }

    // write access denied
    public function testProcessDatumDestroyNoWriteAccess()
    {
        $this->expectException('Exception');
        $controller = new SecureCanaryRequestHandler();
        $controller->processDatumDestroy([
            'ID' => '1',
        ]);
    }

    // missing key
    public function testProcessDatumDestroyNoKey()
    {
        $this->expectException('Exception');
        $controller = new CanaryRequestHandler();
        $controller->processDatumDestroy([
            'fake' => 'fake',
        ]);
    }

    // failed delete cause table locked (fires a database error)
    public function testProcessDatumDestroyFailed()
    {
        DB::nonQuery('LOCK TABLES `canaries` READ');
        $this->expectException('Exception');
        $controller = new CanaryRequestHandler();
        $controller->processDatumDestroy([
            'ID' => '1',
        ]);
    }
}
