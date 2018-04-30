<?php
namespace Divergence\Tests\Models;

use Divergence\Tests\MockSite\Models\Tag;

use Divergence\Models\ActiveRecord;
use Divergence\Models\Model;
use Divergence\Tests\TestUtils;

use PHPUnit\Framework\TestCase;


class ActiveRecordTest extends TestCase
{

    public function setUp() {
        //App::init();
        //$x = Tag::create();
        //xdump($x);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__construct
     * @covers Divergence\Models\ActiveRecord::init
     */
    public function test__construct() {
        $A = new Tag([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ],true,true);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux"
        ],$A->data);
        $this->assertEquals(true,$A->isDirty);
        $this->assertEquals(true,$A->isPhantom);
        $this->assertEquals(true,$A->wasPhantom);
        $this->assertEquals(false,$A->isNew);
        $this->assertEquals(false,$A->isUpdated);
        $this->assertEquals(true,$A->isValid);
        $this->assertEquals([],$A->originalValues);

        $this->assertEquals(true,Tag::getProtected('_fieldsDefined')[Tag::class]);
        $this->assertEquals(false,Tag::getProtected('_relationshipsDefined')[Tag::class]); // we didn't include use \Divergence\Models\Relations when defining the class so it should be false
        $this->assertEquals(true,Tag::getProtected('_eventsDefined')[Tag::class]);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__get
     * @covers Divergence\Models\ActiveRecord::getValue
     * @covers Divergence\Models\ActiveRecord::_getFieldValue
     * @covers Divergence\Models\ActiveRecord::getData
     * @covers Divergence\Models\ActiveRecord::_fieldExists
     */
    public function test__get() {
        $A = new Tag([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ],true,true);
        $this->assertEquals(true,$A->isDirty);
        $this->assertEquals(true,$A->isPhantom);
        $this->assertEquals(true,$A->wasPhantom);
        $this->assertEquals(false,$A->isNew);
        $this->assertEquals(false,$A->isUpdated);
        $this->assertEquals(true,$A->isValid);
        $this->assertEquals([],$A->originalValues);
        $this->assertEquals([],$A->validationErrors);
        $this->assertEquals('Linux',$A->Tag);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux"
        ],$A->data);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux"
        ],$A->getData());
        $this->assertEquals('linux',$A->Slug);
        $this->assertEquals(null,$A->fake);
        $this->assertEquals(null,$A->Handle);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__set
     * @covers Divergence\Models\ActiveRecord::setFields
     * @covers Divergence\Models\ActiveRecord::setField
     * @covers Divergence\Models\ActiveRecord::_setFieldValue
     * @covers Divergence\Models\ActiveRecord::setValue
     * @covers Divergence\Models\ActiveRecord::_cn
     * @covers Divergence\Models\ActiveRecord::_fieldExists
     */
    public function test__set() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals('linux',$A->Slug);
        $A->Slug = 'abc123';
        $this->assertEquals('abc123',$A->Slug);
        $A->setField('Slug','xyz123');
        $this->assertEquals('xyz123',$A->Slug);
        $A->setFields([
            'Tag' => 'OSX',
            'Slug' => 'osx'
        ]);
        $this->assertEquals('osx',$A->Slug);
        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            'Tag' => 'OSX',
            'Slug' => 'osx'
        ],$A->getData());
        $A->fakefield = 'test';
        $this->assertEquals('osx',$A->Slug);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getPrimaryKey
     */
    public function testGetPrimaryKey() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);

        $this->assertEquals(null,$A->getPrimaryKey());
        Tag::$primaryKey = 'Tag';
        $this->assertEquals('Linux',$A->getPrimaryKey());
        Tag::$primaryKey = null;
    }

    /**
     * @covers Divergence\Models\ActiveRecord::create
     */
    public function testCreate() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);

        $this->assertEquals([
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux"
        ],$A->data);

        $this->assertInstanceOf(ActiveRecord::class,$A);
        $this->assertInstanceOf(Model::class,$A);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::__isset
     */
    public function testIsset() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals(isset($A->Tag),true);
        $this->assertEquals(isset($A->fakefield),false);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isVersioned
     */
    public function testIsVersioned() {
        $this->assertEquals(Tag::isVersioned(),false);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isRelational
     */
    public function testIsRelational() {
        $this->assertEquals(Tag::isRelational(),false);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isA
     */
    public function testIsA() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals($A->isA(Tag::class),true);
        $this->assertEquals($A->isA(ActiveRecord::class),true);
        $this->assertEquals($A->isA(Model::class),true);
    }
    
    /**
     * @covers Divergence\Models\ActiveRecord::changeClass
     */
    public function testChangeClass() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertInstanceOf(ActiveRecord::class,$A);
        $this->assertInstanceOf(Model::class,$A);
        $B = $A->changeClass(ActiveRecord::class);
        $this->assertNotInstanceOf(Tag::class,$B);
        $this->assertNotInstanceOf(Model::class,$B);
        $this->assertInstanceOf(ActiveRecord::class,$B);
        $C = $B->changeClass();
        $this->assertInstanceOf(ActiveRecord::class,$B);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getOriginalValue
     */
    public function testGetOriginalValue() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals($A->getOriginalValue('Tag'),null);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getClassFields
     */
    public function testGetClassFields() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
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
            "columnName" => "ID"
          ],$classFields['ID']);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getFieldOptions
     */
    public function testGetFieldOptions() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
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
            "columnName" => "ID"
        ],$A->getFieldOptions('ID'));
        $this->assertEquals('integer',$A->getFieldOptions('ID','type'));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getColumnName
     */
    public function testGetColumnName() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals('ID',$A->getColumnName('ID'));
        $this->assertEquals('Tag',$A->getColumnName('Tag'));

        $this->expectException('Exception');
        $A->getColumnName('nohere');
    }
    
    /**
     * @covers Divergence\Models\ActiveRecord::getRootClass
     */
    public function testGetRootClass() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals(Tag::class,$A->getRootClass());
    }
    
    /**
     * @covers Divergence\Models\ActiveRecord::addValidationErrors
     * @covers Divergence\Models\ActiveRecord::addValidationError
     * @covers Divergence\Models\ActiveRecord::getValidationError
     */
    public function testAddValidationErrors() {
        $A = Tag::create([
            'Tag' => '',
            'Slug' => 'li nux'
        ]);
        $A->addValidationErrors([
            'Tag' => 'Empty',
            'Slug' => 'Contains space'
        ]);
        $this->assertEquals('Empty',($A->getValidationError('Tag')));
        $this->assertEquals('Contains space',($A->getValidationError('Slug')));
        $this->assertEquals(null,($A->getValidationError('fake')));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::isFieldDirty
     */
    public function testIsFieldDirty() {
        $A = Tag::create([
            'Tag' => 'Linux',
            'Slug' => 'linux'
        ]);
        $this->assertEquals(true,$A->isFieldDirty('Tag'));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::_getRecordClass
     */
    public function test_getRecordClass() {
        $record = [
            "ID" => null,
            "Class" => "Divergence\Tests\MockSite\Models\Tag",
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Tag" => "Linux",
            "Slug" => "linux"
        ];
        

        $this->assertEquals('Divergence\Tests\MockSite\Models\Tag',Tag::getRecordClass($record));
        $this->assertEquals('Divergence\Tests\MockSite\Models\Tag',Tag::getRecordClass([]));
    }

    /**
     * @covers Divergence\Models\ActiveRecord::save
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     */
    public function testSave() {
        TestUtils::requireDB($this);

        $x = Tag::create(['Tag'=>'deleteMe','Slug'=>'deleteme']);
        $this->assertEquals('deleteMe',$x->Tag);
        $this->assertEquals(true,$x->isPhantom);
        $this->assertEquals(true,$x->isDirty);
        $x->save();
        $this->assertEquals(false,$x->isPhantom);
        $this->assertEquals(false,$x->isDirty);
        $x->Tag = 'changed';
        $this->assertEquals(false,$x->isPhantom);
        $this->assertEquals(true,$x->isDirty);
        $x->save();
        $this->assertEquals('changed',$x->Tag);
        $x->destroy();
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByHandle
     * @covers Divergence\Models\ActiveRecord::getByID
     */
    public function testGetByHandle() {
        TestUtils::requireDB($this);

        $x = Tag::getByHandle(1);
        $this->assertEquals('Linux',$x->Tag);
    }

    /**
     * @covers Divergence\Models\ActiveRecord::getByField
     * @covers Divergence\Models\ActiveRecord::getByID
     * @covers Divergence\Models\ActiveRecord::destroy
     * @covers Divergence\Models\ActiveRecord::delete
     */
    public function testGetByField() {
        TestUtils::requireDB($this);
        
        $x = Tag::getByField('Tag','Linux');
        $this->assertEquals('Linux',$x->Tag);

        $a = Tag::create(['Tag'=>'first','Slug'=>'deleteme'],true);
        $b = Tag::create(['Tag'=>'second','Slug'=>'deleteme'],true);

        $c = Tag::getByField('Slug','deleteme');
        $this->assertEquals('first',$c->Tag);
        $a->destroy();
        $b->destroy();
    }
}