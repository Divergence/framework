<?php
namespace Divergence\Tests\Models;

use Divergence\Tests\MockSite\Models\Tag;

use Divergence\Models\ActiveRecord;
use Divergence\Models\Model;

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
        $this->assertEquals('linux',$A->Slug);
        $this->assertEquals(null,$A->fake);
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
    
}