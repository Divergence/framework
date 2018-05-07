<?php
namespace Divergence\Tests\Models;

use Divergence\Models\Model;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Versioning;
use Divergence\Models\ActiveRecord;


use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;

class fakeCanary extends Canary
{ /* so we can test init on a brand new class */
    use Versioning;

    public static $nextErrorAsException = false;
    public static function handleError($query = null, $queryLog = null, $parameters = null)
    {
        if (static::$nextErrorAsException) {
            static::$nextErrorAsException = false;
            throw new \Exception('fakeCanary handlError exception');
        }
        return parent::handleError($query, $queryLog = null, $parameters = null);
    }
    public static function throwExceptionNextError()
    {
        static::$nextErrorAsException = true;
    }
}

class ActiveRecordTest extends TestCase
{
    public function setUp()
    {
        //App::init();
        //$x = Tag::create();
        //xdump($x);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__construct
     * @covers Divergence\Models\ActiveRecord::init
     * @covers Divergence\Models\ActiveRecord::_defineEvents
     * @covers Divergence\Models\ActiveRecord::_defineFields
     * @covers Divergence\Models\ActiveRecord::_initFields
     */
    public function test__construct()
    {
        TestUtils::requireDB($this);

        $A = new Tag([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ], true, true);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux",
        ], $A->data);
        $this->assertEquals(true, $A->isDirty);
        $this->assertEquals(true, $A->isPhantom);
        $this->assertEquals(true, $A->wasPhantom);
        $this->assertEquals(false, $A->isNew);
        $this->assertEquals(false, $A->isUpdated);
        $this->assertEquals(true, $A->isValid);
        $this->assertEquals([], $A->originalValues);

        $this->assertEquals(false, fakeCanary::getProtected('_fieldsDefined')[fakeCanary::class]);
        $this->assertEquals(false, fakeCanary::getProtected('_relationshipsDefined')[fakeCanary::class]); // we didn't include use \Divergence\Models\Relations when defining the class so it should be false
        $this->assertEquals(false, fakeCanary::getProtected('_eventsDefined')[fakeCanary::class]);

        $x = fakeCanary::create(fakeCanary::avis(), false);

        $this->assertEquals(true, Tag::getProtected('_fieldsDefined')[Tag::class]);
        $this->assertEquals(false, Tag::getProtected('_relationshipsDefined')[Tag::class]); // we didn't include use \Divergence\Models\Relations when defining the class so it should be false
        $this->assertEquals(true, Tag::getProtected('_eventsDefined')[Tag::class]);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__get
     * @covers Divergence\Models\ActiveRecord::getValue
     * @covers Divergence\Models\ActiveRecord::_getFieldValue
     * @covers Divergence\Models\ActiveRecord::getData
     * @covers Divergence\Models\ActiveRecord::fieldExists
     */
    public function test__get()
    {
        $A = new Tag([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ], true, true);
        $this->assertEquals(true, $A->isDirty);
        $this->assertEquals(true, $A->isPhantom);
        $this->assertEquals(true, $A->wasPhantom);
        $this->assertEquals(false, $A->isNew);
        $this->assertEquals(false, $A->isUpdated);
        $this->assertEquals(true, $A->isValid);
        $this->assertEquals([], $A->originalValues);
        $this->assertEquals([], $A->validationErrors);
        $this->assertEquals('Linux', $A->Tag);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux",
        ], $A->data);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux",
        ], $A->getData());
        $this->assertEquals('linux', $A->Slug);
        $this->assertEquals(null, $A->fieldExists('fake'));
        $this->assertEquals(null, $A->fake);
        $this->assertEquals(null, $A->Handle);

        $A->addValidationErrors([
            'Tag' => 'Empty',
            'Slug' => 'Contains space',
        ]);

        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux",
            'validationErrors' => [
                'Tag' => 'Empty',
                'Slug' => 'Contains space',
            ],
        ], $A->getData());
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__set
     * @covers Divergence\Models\ActiveRecord::setFields
     * @covers Divergence\Models\ActiveRecord::setField
     * @covers Divergence\Models\ActiveRecord::_setFieldValue
     * @covers Divergence\Models\ActiveRecord::setValue
     * @covers Divergence\Models\ActiveRecord::_cn
     * @covers Divergence\Models\ActiveRecord::fieldExists
     * @covers Divergence\Models\ActiveRecord::getData
     */
    public function test__set()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals('linux', $A->Slug);
        $A->Slug = 'abc123';
        $this->assertEquals('abc123', $A->Slug);
        $A->setField('Slug', 'xyz123');
        $this->assertEquals('xyz123', $A->Slug);
        $A->setFields([
            'Tag' => 'OSX',
            'Slug' => 'osx',
        ]);
        $this->assertEquals('osx', $A->Slug);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            'Tag' => 'OSX',
            'Slug' => 'osx',
        ], $A->getData());
        $A->fakefield = 'test';
        $this->assertEquals('osx', $A->Slug);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getPrimaryKey
     */
    public function testGetPrimaryKey()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);

        $this->assertEquals(null, $A->getPrimaryKey());
        Tag::$primaryKey = 'Tag';
        $this->assertEquals('Linux', $A->getPrimaryKey());
        Tag::$primaryKey = null;
    }

    /**
     * @covers Divergence\Models\ActiveRecord::create
     */
    public function testCreate()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);

        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux",
        ], $A->data);

        $this->assertInstanceOf(ActiveRecord::class, $A);
        $this->assertInstanceOf(Model::class, $A);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__isset
     */
    public function testIsset()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals(isset($A->Tag), true);
        $this->assertEquals(isset($A->fakefield), false);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isVersioned
     */
    public function testIsVersioned()
    {
        $this->assertEquals(Tag::isVersioned(), false);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isRelational
     */
    public function testIsRelational()
    {
        $this->assertEquals(Tag::isRelational(), false);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isA
     */
    public function testIsA()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals($A->isA(Tag::class), true);
        $this->assertEquals($A->isA(ActiveRecord::class), true);
        $this->assertEquals($A->isA(Model::class), true);
    }
    
    /**
     * @covers Divergence\Models\ActiveRecord::changeClass
     */
    public function testChangeClass()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertInstanceOf(ActiveRecord::class, $A);
        $this->assertInstanceOf(Model::class, $A);
        $B = $A->changeClass(ActiveRecord::class);
        $this->assertNotInstanceOf(Tag::class, $B);
        $this->assertNotInstanceOf(Model::class, $B);
        $this->assertInstanceOf(ActiveRecord::class, $B);
        $C = $B->changeClass();
        $this->assertInstanceOf(ActiveRecord::class, $B);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::changeClass
     */
    public function testChangeClassWithData()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $expected = ['Tag'=>'Graphics','Slug'=>'graphics'];
        $C = $A->changeClass(Tag::class,$expected);
        $this->assertArraySubset($expected,$C->data);
        
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getOriginalValue
     */
    public function testGetOriginalValue()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals($A->getOriginalValue('Tag'), null);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getClassFields
     */
    public function testGetClassFields()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $classFields = $A->getClassFields();
        $this->assertEquals([
            "type" => "integer",
            "length" => null,
            "primary" => true,
            "unique" => null,
            "autoincrement" => true,
            "notnull" => true,
            "unsigned" => true,
            "default" => null,
            "values" => null,
            "columnName" => "ID",
          ], $classFields['ID']);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getFieldOptions
     */
    public function testGetFieldOptions()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals([
            "type" => "integer",
            "length" => null,
            "primary" => true,
            "unique" => null,
            "autoincrement" => true,
            "notnull" => true,
            "unsigned" => true,
            "default" => null,
            "values" => null,
            "columnName" => "ID",
        ], $A->getFieldOptions('ID'));
        $this->assertEquals('integer', $A->getFieldOptions('ID', 'type'));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::mapFieldOrder
     * @covers Divergence\Models\ActiveRecord::_mapFieldOrder
     */
    public function testMapFieldOrder()
    {
        $x = ActiveRecord::mapFieldOrder('some string');
        $this->assertEquals(['some string'], $x);
        
        $x = Tag::mapFieldOrder([
            'Tag' => 'DESC',
            'Created' => 'ASC',
            'ID', // should default to ASC when direction not provided
        ]);
        $this->assertEquals([
            0 => "`Tag` DESC",
            1 => "`Created` ASC",
            2 => "`ID` ASC",
        ], $x);

        $this->assertEquals(Tag::mapFieldOrder(new \stdClass()), null);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::mapFieldOrder
     * @covers Divergence\Models\ActiveRecord::_mapFieldOrder
     */
    public function testMapFieldOrderNonExistantColumn()
    {
        $this->expectExceptionMessage('getColumnName called on nonexisting column: Divergence\Tests\MockSite\Models\Tag->some string');
        $x = Tag::mapFieldOrder(['some string','bleh']);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::mapConditions
     * @covers Divergence\Models\ActiveRecord::_mapConditions
     */
    public function testMapConditions()
    {
        TestUtils::requireDB($this);

        $conditions = [
            'Handle' => null,
            'Name' => [
                'operator' => 'NOT',
                'value' => "Frank",
            ],
            'isAlive' => true,
        ];
        
        $this->assertEquals([
            "Handle" => "`Handle` IS NULL",
            "Name" => "`Name` NOT \"Frank\"",
            "isAlive" => "`isAlive` = \"1\"",
        ], Canary::mapConditions($conditions));

        $conditions = [
            'Handle' => '',
            'Name' => [
                'operator' => 'NOT',
                'value' => "Frank",
            ],
            'isAlive' => true,
        ];

        $this->assertEquals([
            "Handle" => "`Handle` IS NULL",
            "Name" => "`Name` NOT \"Frank\"",
            "isAlive" => "`isAlive` = \"1\"",
        ], Canary::mapConditions($conditions));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getColumnName
     */
    public function testGetColumnName()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals('ID', $A->getColumnName('ID'));
        $this->assertEquals('Tag', $A->getColumnName('Tag'));

        $this->expectException('Exception');
        $A->getColumnName('nohere');
    }
    
    /**
     * @covers Divergence\Models\ActiveRecord::getRootClass
     */
    public function testGetRootClass()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals(Tag::class, $A->getRootClass());
    }
    
    /**
     * @covers Divergence\Models\ActiveRecord::addValidationErrors
     * @covers Divergence\Models\ActiveRecord::addValidationError
     * @covers Divergence\Models\ActiveRecord::getValidationError
     */
    public function testAddValidationErrors()
    {
        $A = Tag::create([
            'Tag' => '',
            'Slug' => 'li nux',
        ]);
        $A->addValidationErrors([
            'Tag' => 'Empty',
            'Slug' => 'Contains space',
        ]);
        $this->assertEquals('Empty', ($A->getValidationError('Tag')));
        $this->assertEquals('Contains space', ($A->getValidationError('Slug')));
        $this->assertEquals(null, ($A->getValidationError('fake')));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isFieldDirty
     */
    public function testIsFieldDirty()
    {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux',
        ]);
        $this->assertEquals(true, $A->isFieldDirty('Tag'));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::_getRecordClass
     */
    public function test_getRecordClass()
    {
        $record = [
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux",
        ];
        

        $this->assertEquals('Divergence\Tests\MockSite\Models\Tag', Tag::getRecordClass($record));
        $this->assertEquals('Divergence\Tests\MockSite\Models\Tag', Tag::getRecordClass([]));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::save
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     * @covers Divergence\Models\ActiveRecord::validate
     * @covers Divergence\Models\ActiveRecord::finishValidation
     * @covers Divergence\Models\ActiveRecord::_prepareRecordValues
     *
     */
    public function testSave()
    {
        TestUtils::requireDB($this);

        $x = Tag::create(['Tag'=>'deleteMe','Slug'=>'deleteme']);
        $this->assertEquals('deleteMe', $x->Tag);
        $this->assertEquals(true, $x->isPhantom);
        $this->assertEquals(true, $x->isDirty);
        $x->save();
        $this->assertEquals(false, $x->isPhantom);
        $this->assertEquals(false, $x->isDirty);
        $x->Tag = 'changed';
        $this->assertEquals(false, $x->isPhantom);
        $this->assertEquals(true, $x->isDirty);
        $x->save();
        $this->assertEquals('changed', $x->Tag);
        $x->destroy();
    }

    /**
     * @covers Divergence\Models\ActiveRecord::save
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     * @covers Divergence\Models\ActiveRecord::validate
     * @covers Divergence\Models\ActiveRecord::create
     * @covers Divergence\Models\ActiveRecord::setFields
     * @covers Divergence\Models\ActiveRecord::__construct
     * @covers Divergence\Models\ActiveRecord::_getFieldValue
     * @covers Divergence\Models\ActiveRecord::_setFieldValue
     * @covers Divergence\Models\ActiveRecord::_mapValuesToSet
     * @covers Divergence\Models\ActiveRecord::_prepareRecordValues
     */
    public function testSaveCanary()
    {
        $data = Canary::avis();
        $data['DateOfBirth'] = date('Y-m-d', $data['DateOfBirth']);
        $Canary = Canary::create($data);
        $Canary->setFields($data);
        
        $returnData = $Canary->data;
        
        // fix this later
        unset($data['Colors']);
        unset($returnData['Colors']);

        $this->assertArraySubset($data, $returnData);
        
        $data = Canary::avis();
        $data['DateOfBirth'] = date('Y-m-d', $data['DateOfBirth']);
        $Canary = new Canary();
        $Canary->setFields($data);
        
        $Canary->save();
        $returnData = $Canary->data;
        
        // fix this later
        unset($data['Colors']);
        unset($returnData['Colors']);

        $this->assertArraySubset($data, $returnData);

        // ignore set when
        // $fieldOptions['autoincrement'] = true
        $x = Canary::getByID(1);
        $x->ID = 5;
        $this->assertEquals(1, $x->ID);

        // null value instead of empty string when
        // $fieldOptions['notnull'] = false
        // $fieldOptions['blankisnull] = true
        $x->Handle = null;
        $this->assertNull($x->Handle);
        

        // null value for int when
        // $fieldOptions['notnull'] = false
        $x->LongestFlightTime = null;
        $this->assertNull($x->LongestFlightTime);

        $x->Created = '2018-05-02 03:48:07';
        $this->assertEquals($x->Created, strtotime('2018-05-02 03:48:07'));

        $x->StatusCheckedLast = 1;
        $this->assertEquals(date("Y-m-d H:i:s", $x->StatusCheckedLast), date("Y-m-d H:i:s", 1));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::save
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     * @covers Divergence\Models\ActiveRecord::validate
     * @covers Divergence\Models\ActiveRecord::finishValidation
     * @covers Divergence\Models\ActiveRecord::_getFieldValue
     * @covers Divergence\Models\ActiveRecord::_setFieldValue
     * @covers Divergence\Models\ActiveRecord::_mapValuesToSet
     * @covers Divergence\Models\ActiveRecord::_prepareRecordValues
     */
    public function testSaveCanaryTimestamps()
    {
        $x = Canary::getByID(1);
        $x->StatusCheckedLast = 'last Monday';
        $this->assertEquals(date("Y-m-d H:i:s", $x->StatusCheckedLast), date("Y-m-d H:i:s", strtotime('last monday')));

        // dates
        $x = Canary::getByID(1);
        $x->DateOfBirth = time();
        $this->assertEquals($x->DateOfBirth, date('Y-m-d'));

        $x->DateOfBirth = date('m/d/Y', strtotime('last week'));
        $this->assertEquals($x->DateOfBirth, date('Y-m-d', strtotime('last week')));

        $x->DateOfBirth = date('Y-m-d', strtotime('last Monday'));
        $this->assertEquals($x->DateOfBirth, date('Y-m-d', strtotime('last Monday')));

        $x->DateOfBirth = [
            'yyyy' => date('Y'),
            'mm' => date('m'),
            'dd' => date('d'),
        ];
        $this->assertEquals($x->DateOfBirth, date('Y-m-d'));

        $x->DateOfBirth = null;
        $this->assertNull($x->DateOfBirth);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByContext
     * @covers Divergence\Models\ActiveRecord::getByContextObject
     * @covers Divergence\Models\ActiveRecord::getRecordByWhere
     * @covers Divergence\Models\ActiveRecord::_getRecordClass
     * @covers Divergence\Models\ActiveRecord::fieldExists
     */
    public function testGetByContextObject()
    {
        TestUtils::requireDB($this);

        $Tag = Tag::getByID(7);

        $x = Canary::getByContextObject($Tag);
        $this->assertEquals(1, $x->ID);

        $x = Canary::getByContextObject($Tag, ['order'=>['ID'=>'DESC']]);
        $this->assertEquals(DB::oneValue("SELECT `id` FROM canaries ORDER BY ID DESC"), $x->ID);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByContext
     * @covers Divergence\Models\ActiveRecord::getRecordByWhere
     * @covers Divergence\Models\ActiveRecord::_getRecordClass
     * @covers Divergence\Models\ActiveRecord::fieldExists
     */
    public function testGetByContextException()
    {
        TestUtils::requireDB($this);

        $this->expectExceptionMessage('getByContext requires the field ContextClass to be defined');
        Tag::getByContext(Tag::class, 7);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByContext
     * @covers Divergence\Models\ActiveRecord::getAllByContextObject
     * @covers Divergence\Models\ActiveRecord::getRecordByWhere
     * @covers Divergence\Models\ActiveRecord::_getRecordClass
     * @covers Divergence\Models\ActiveRecord::fieldExists
     */
    public function testGetAllByContextObject()
    {
        TestUtils::requireDB($this);

        $Tag = Tag::getByID(7);

        $x = Canary::getAllByContextObject($Tag);
    
        $this->assertEquals(DB::oneValue("SELECT COUNT(*) FROM canaries"), count($x));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByContext
     * @covers Divergence\Models\ActiveRecord::getRecordByWhere
     * @covers Divergence\Models\ActiveRecord::_getRecordClass
     * @covers Divergence\Models\ActiveRecord::fieldExists
     */
    public function testGetAllByContextException()
    {
        TestUtils::requireDB($this);

        $this->expectExceptionMessage('getByContext requires the field ContextClass to be defined');
        Tag::getAllByContext(Tag::class, 7);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByHandle
     * @covers Divergence\Models\ActiveRecord::getByID
     */
    public function testGetByHandle()
    {
        TestUtils::requireDB($this);

        $x = Tag::getByHandle(1);
        $this->assertEquals('Linux', $x->Tag);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByField
     * @covers Divergence\Models\ActiveRecord::getByID
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     * @covers Divergence\Models\ActiveRecord::getRecordByField
     * @covers Divergence\Models\ActiveRecord::instantiateRecord
     */
    public function testGetByField()
    {
        TestUtils::requireDB($this);
        
        $x = Tag::getByField('Tag', 'Linux');
        $this->assertEquals('Linux', $x->Tag);

        $a = Tag::create(['Tag'=>'first','Slug'=>'deleteme'], true);
        $b = Tag::create(['Tag'=>'second','Slug'=>'deleteme'], true);

        $c = Tag::getByField('Slug', 'deleteme');
        $this->assertEquals('first', $c->Tag);
        $a->destroy();
        $b->destroy();
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByWhere
     * @covers Divergence\Models\ActiveRecord::getRecordByWhere
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     * @covers Divergence\Models\ActiveRecord::instantiateRecord
     * @covers Divergence\Models\ActiveRecord::_mapConditions
     * @covers Divergence\Models\ActiveRecord::_mapFieldOrder
     */
    public function testGetByWhere()
    {
        TestUtils::requireDB($this);

        $a = Tag::create(['Tag'=>'first','Slug'=>'deleteme'], true);
        $b = Tag::create(['Tag'=>'second','Slug'=>'deleteme'], true);

        $search = Tag::getByWhere(['Slug'=>'deleteme'], ['order'=>['Tag'=>'ASC']]);

        $this->assertEquals($a->data, $search->data);

        $search = Tag::getByWhere(['Slug'=>'deleteme'], ['order'=>['Tag'=>'DESC']]);
        $this->assertEquals($b->data, $search->data);

        // by string in array
        $search = Tag::getByWhere(["Tag in ('first','second')"], [
            'order'=> [
                'ID'=>'DESC',
            ],
        ]);
        $this->assertEquals($b->data, $search->data);

        // by string
        $search = Tag::getByWhere("Tag in ('first','second')", [
            'order'=> [
                'ID'=>'DESC',
            ],
        ]);
        $this->assertEquals($b->data, $search->data);

        $a->destroy();
        $b->destroy();
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByQuery
     */
    public function testGetByQuery()
    {
        TestUtils::requireDB($this);

        $x = Tag::getByQuery("SELECT * FROM `tags` WHERE Tag in ('%s','%s') ORDER BY ID DESC LIMIT 1", [
            'Linux','PHPUnit',
        ]);
        $this->assertEquals('PHPUnit', $x->Tag);

        $x = Tag::getByQuery("SELECT * FROM `tags` WHERE Tag in ('%s','%s') ORDER BY ID ASC LIMIT 1", [
            'Linux','PHPUnit',
        ]);
        $this->assertEquals('Linux', $x->Tag);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByClass
     * @covers Divergence\Models\ActiveRecord::getAllByField
     */
    public function testGetAllByClass()
    {
        TestUtils::requireDB($this);

        $x = Tag::getAllByClass();
        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM tags'), count($x));
        $y = Tag::getAllByClass(Tag::class);
        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM tags'), count($y));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByField
     * @covers Divergence\Models\ActiveRecord::getAllByWhere
     * @covers Divergence\Models\ActiveRecord::getAllRecordsByWhere
     */
    public function testGetAllByField()
    {
        TestUtils::requireDB($this);

        $x = Tag::getAllByField('CreatorID', 1);
        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM tags WHERE CreatorID=1'), count($x));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByWhere
     * @covers Divergence\Models\ActiveRecord::getAllRecordsByWhere
     * @covers Divergence\Models\ActiveRecord::instantiateRecords
     * @covers Divergence\Models\ActiveRecord::mapFieldOrder
     * @covers Divergence\Models\ActiveRecord::_mapFieldOrder
     */
    public function testGetAllByWhere()
    {
        TestUtils::requireDB($this);

        $x = Tag::getAllByWhere(['CreatorID'=>1]);
        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM tags WHERE CreatorID=1'), count($x));

        // condition as string
        $x = Tag::getAllByWhere('CreatorID=1');
        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM tags WHERE CreatorID=1'), count($x));

        // extraColumns as array
        
        $x = Canary::getAllByWhere(['Class'=>Canary::class], [
            'extraColumns' =>[
                'HeightInInches'=>'format(`Height`/2.54,2)',
            ],
            'limit' => 2,
        ]);
        $RawRecord =$x[0]->getRecord();

        $this->assertEquals(2, count($x));
        $this->assertContainsOnlyInstancesOf(Canary::class, $x);

        $Expectation = number_format($RawRecord['Height']/2.54, 2);
        $this->assertEquals($Expectation, $RawRecord['HeightInInches']);

        // extraColumns as string
        $x = Canary::getAllByWhere(['Class'=>Canary::class], [
            'extraColumns' => 'format(Height/2.54,2) as HeightInInches',
            'limit' => 3,
        ]);
        $RawRecord =$x[0]->getRecord();

        $this->assertEquals(3, count($x));
        $this->assertContainsOnlyInstancesOf(Canary::class, $x);

        $Expectation = number_format($RawRecord['Height']/2.54, 2);
        $this->assertEquals($Expectation, $RawRecord['HeightInInches']);

        // having
        $x = Canary::getAllByWhere(['Class'=>Canary::class], [
            'extraColumns' => 'format(Height/2.54,2) as HeightInInches',
            'having' => [
                '`HeightInInches`>5.0',
            ],
        ]);
        
        $expectedCount = DB::oneValue("SELECT COUNT(*) FROM ( SELECT format(`Height`/2.54,2) as `HeightInInches` FROM `canaries` HAVING `HeightInInches`>5 ) x");

        $this->assertEquals($expectedCount, count($x));
        $this->assertContainsOnlyInstancesOf(Canary::class, $x);

        // having
        $x = Canary::getAllByWhere(['Class'=>Canary::class], [
            'extraColumns' => 'format(Height/2.54,2) as HeightInInches',
            'having' => '`HeightInInches`>5.0',
            /* getAllByWhere fires _mapConditions on having when it's passed like this but because the field value below is an alias the _mapConditions function won't work.
            // It checks if the fieldExists and ignores it if it's not part of the model
            'having' => [
                [
                    'field'=>'HeightInInches',
                    'operator' => '>',
                    'value' => '5.0',
                ]
            ]*/
        ]);
        
        $expectedCount = DB::oneValue("SELECT COUNT(*) FROM ( SELECT format(`Height`/2.54,2) as `HeightInInches` FROM `canaries` HAVING `HeightInInches`>5 ) x");

        $this->assertEquals($expectedCount, count($x));
        $this->assertContainsOnlyInstancesOf(Canary::class, $x);

        // order as string
        $x = Canary::getAllByWhere(['Class'=>Canary::class], ['order'=> 'Name DESC']);
        $firstNameZeroPosChar = ord($x[0]->Name[0]);
        $lastNameZeroPosChar = ord($x[count($x)-1]->Name[0]);
        $this->assertGreaterThan($lastNameZeroPosChar, $firstNameZeroPosChar);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAll
     * @covers Divergence\Models\ActiveRecord::getAllRecords
     * @covers Divergence\Models\ActiveRecord::instantiateRecords
     */
    public function testGetAll()
    {
        TestUtils::requireDB($this);

        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM tags'), count(Tag::getAll()));

        // order
        $x = Canary::getAll(['order'=> 'Name DESC']);
        $firstNameZeroPosChar = ord($x[0]->Name[0]);
        $lastNameZeroPosChar = ord($x[count($x)-1]->Name[0]);
        $this->assertGreaterThan($lastNameZeroPosChar, $firstNameZeroPosChar);

        // limit
        $x = Canary::getAll(['limit'=>5]);
        $this->assertEquals(5, count($x));

        // indexField
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByQuery
     * @covers Divergence\Models\ActiveRecord::instantiateRecords
     */
    public function testGetAllByQuery()
    {
        TestUtils::requireDB($this);

        $x = Canary::getAllByQuery('SELECT * FROM canaries LIMIT 4');
        $this->assertEquals(4, count($x));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::instantiateRecords
     * @covers Divergence\Models\ActiveRecord::getTableByQuery
     * @covers Divergence\IO\Database\MySQL::table
     */
    public function testGetTableByQuery()
    {
        $x = Canary::getTableByQuery('ID','SELECT * FROM canaries');
        $expected = DB::allRecords('SELECT * FROM canaries');
        foreach($expected as $record) {
            $ID = $record['ID'];
            $this->assertEquals($x[$ID]->ID,$ID);
        }
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getUniqueHandle

     */
    public function testGetUniqueHandle()
    {
        TestUtils::requireDB($this);

        $x = Canary::getByID(1);
        
        $newHandle = Canary::getUniqueHandle($x->Handle);

        $lastChar = substr($x->Handle, -1);
        if (intval($lastChar)) {
            $expectedHandle = str_replace(substr($x->Handle, -1), (intval($lastChar)+1), $x->Handle);
        } else {
            $expectedHandle = $x->Handle.':2';
        }

        $this->assertEquals($expectedHandle, $newHandle);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::generateRandomHandle

     */
    public function testGenerateRandomHandle()
    {
        TestUtils::requireDB($this);
        
        $x = Canary::generateRandomHandle(1);
        //$y = Canary::generateRandomHandle(100);
        $z = Canary::generateRandomHandle();



        $this->assertEquals(1, strlen($x));
        //$this->assertEquals(100, strlen($y));
        $this->assertEquals(32, strlen($z));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::validate
     * @covers Divergence\Models\ActiveRecord::finishValidation
     */
    // needs work
    /*public function testValidate()
    {
        TestUtils::requireDB($this);

        $A = new Tag([
            'Tag' => 'Li nux',
            'Slug' => 'linux',
        ]);

        $A->addValidationError('Tag','Has a space in it');
        $A->save();
    }*/

    /**
     * @covers Divergence\Models\ActiveRecord::getAllByQuery
     * @covers Divergence\Models\ActiveRecord::handleError
     */
    public function testGetAllByQueryException()
    {
        TestUtils::requireDB($this);

        $this->expectException(\RunTimeException::class);
        Canary::getAllByQuery('SELECT nothing(*)');
    }

    /**
     * @covers Divergence\Models\ActiveRecord::handleError
     * @covers Divergence\IO\Database\MySQL::handleError
     */
    public function testHandleError()
    {
        TestUtils::requireDB($this);

        
        fakeCanary::throwExceptionNextError();
        $this->expectExceptionMessage('fakeCanary handlError exception');
        fakeCanary::getAllByQuery('SELECT nothing(*)');
    }

    /**
     * @covers Divergence\Models\ActiveRecord::handleError
     * @covers Divergence\IO\Database\MySQL::handleError
     */
    public function testAutomagicTableCreation()
    {
        TestUtils::requireDB($this);

        $a = Canary::$tableName;
        $b = Canary::$historyTable;

        fakeCanary::$tableName = 'fake';
        fakeCanary::$historyTable = 'history_fake';
        
        $x = fakeCanary::create(fakeCanary::avis(), true);
        
        $this->assertCount(2, DB::allRecords("SHOW TABLES WHERE `Tables_in_test` IN ('fake','history_fake')"));
        fakeCanary::$tableName = $a;
        fakeCanary::$historyTable = $b;
        DB::nonQuery('DROP TABLE `fake`,`history_fake`');
        $this->assertCount(0, DB::allRecords("SHOW TABLES WHERE `Tables_in_test` IN ('fake','history_fake')"));
    }
}
