<?php
namespace Divergence\Tests\Models;

use Divergence\Models\Model;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Models\ActiveRecord;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\MockSite\Models\Forum\Post;
use Divergence\Tests\MockSite\Models\Forum\Thread;
use Divergence\Tests\MockSite\Models\Forum\Category;

class fakeCategory extends Category
{
    use Versioning, Relations;

    public static $relationships = [];

    public static function setClassRelationships($x)
    {
        static::$_classRelationships = $x;
    }

    public static function getClassRelationships()
    {
        return static::$_classRelationships;
    }

    public static function initRelationship($relationship, $options)
    {
        return static::_initRelationship($relationship, $options);
    }
}

class relationalCanary extends fakeCanary
{
    use Versioning,Relations;
}

class RelationsTest extends TestCase
{
    public function test__construct()
    {
        TestUtils::requireDB($this);

        $A = new Category([
            'Name' => 'Art',
        ], true, true);
        $this->assertEquals([
            "ID" => null,
            "Class" => Category::class,
            "Created" => "CURRENT_TIMESTAMP",
            "CreatorID" => null,
            "Name" => "Art",
            "RevisionID" => null,
        ], $A->data);
        $this->assertEquals(true, $A->isDirty);
        $this->assertEquals(true, $A->isPhantom);
        $this->assertEquals(true, $A->wasPhantom);
        $this->assertEquals(false, $A->isNew);
        $this->assertEquals(false, $A->isUpdated);
        $this->assertEquals(true, $A->isValid);
        $this->assertEquals([], $A->originalValues);

        $this->assertEquals(false, fakeCategory::getProtected('_fieldsDefined')[fakeCategory::class]);
        $this->assertEquals(false, fakeCategory::getProtected('_relationshipsDefined')[fakeCategory::class]);
        $this->assertEquals(false, fakeCategory::getProtected('_eventsDefined')[fakeCategory::class]);

        $x = fakeCategory::create(['Name'=>'test'], false);

        $this->assertEquals(true, fakeCategory::getProtected('_fieldsDefined')[fakeCategory::class]);
        $this->assertEquals(true, fakeCategory::getProtected('_relationshipsDefined')[fakeCategory::class]);
        $this->assertEquals(true, fakeCategory::getProtected('_eventsDefined')[fakeCategory::class]);
    }

    // also tests basic one-many
    public function test__get()
    {
        $Category = Category::getByID(1);
        $Threads = $Category->Threads;
        $Expected = Thread::getAllByField('CategoryID', 1);
        $this->assertEquals($Expected, $Threads);
    }

    public function testSave()
    {
        $Category = Category::getByID(1);
        $Expected = Thread::getAllByField('CategoryID', 1);
        foreach ($Expected as $i=>$object) {
            $Expected[$i] = $object->data;
        }
        foreach ($Category->Threads as $Thread) {
            $Threads[] = $Thread->data;
        }
        $Category->save();
        $this->assertEquals($Expected, $Threads);
    }

    public function test_relationshipExistsFalse()
    {
        $this->assertFalse(fakeCategory::_relationshipExists('nope'));
        $x = fakeCategory::getClassRelationships();
        fakeCategory::setClassRelationships([]);
        $this->assertFalse(fakeCategory::_relationshipExists('nope'));
        fakeCategory::setClassRelationships($x);
    }

    public function testOneone()
    {
        $Post = Post::getByID(1);
        $this->assertEquals($Post->ThreadID, $Post->Thread->ID);
    }

    public function testInvalidRelationshipOption()
    {
        $x = fakeCategory::getClassRelationships();
        $x[fakeCategory::class]['somerelation'] = false;
        fakeCategory::setClassRelationships($x);
        $y = fakeCategory::getByID(1);
        $this->assertNull($y->somerelation);
    }

    public function testExplicitOneOne()
    {
        $Post = Post::getByID(1);
        $this->assertEquals($Post->ThreadID, $Post->ThreadExplicit->ID);
    }

    public function testOneManyConditional()
    {
        $Post = Post::getByID(1);
        $Category = Category::getByID(1);
        $Threads = $Category->ThreadsAlpha;
        
        $Expected = Thread::getAllByField('CategoryID', 1, [
            'order' => ['Title'=>'ASC'],
        ]);
        $this->assertEquals($Expected, $Threads);
    }

    public function testInitRelationship()
    {
        $x = fakeCategory::initRelationship('label', []);
        $this->assertEquals([
            'type'=>'one-one',
            'local'=>'labelID',
            'foreign'=>'ID',
        ], $x);

        // one-one with options
        $x = fakeCategory::initRelationship('label', [
            'type' => 'one-one',
            'local' => 'primaryKey',
            'foreign' => 'foreignKey',
        ]);
        $this->assertEquals([
            'type'=>'one-one',
            'local'=>'primaryKey',
            'foreign'=>'foreignKey',
        ], $x);


        // one many default
        $x = fakeCategory::initRelationship('label', [
            'type' => 'one-many',
        ]);
        $this->assertEquals([
            'type'=>'one-many',
            'local'=>'ID',
            'foreign'=>'CategoryID',
            'indexField'=>false,
            'conditions'=>[],
            'order'=>false,
        ], $x);

        // one many test string conditional
        $x = fakeCategory::initRelationship('label', [
            'type' => 'one-many',
            'conditions' => 'true=true',
        ]);
        $this->assertEquals([
            'type'=>'one-many',
            'local'=>'ID',
            'foreign'=>'CategoryID',
            'indexField'=>false,
            'conditions'=>['true=true'],
            'order'=>false,
        ], $x);


        // context-children
        $x = fakeCategory::initRelationship('label', [
            'type'=>'context-children'
        ]);
        $this->assertEquals([
            'type'=>'context-children',
            'local'=>'ID',
            'contextClass'=>fakeCategory::class,
            'indexField'=>false,
            'conditions'=>[],
            'order'=>false,
        ], $x);

        $x = fakeCategory::initRelationship('label', [
            'type'=>'context-children',
            'local'=>'ID',
            'contextClass'=>fakeCategory::class,
            'indexField'=>false,
            'conditions'=>['true'=>'true'],
            'order'=>false,
        ]);
        $this->assertEquals([
            'type'=>'context-children',
            'local'=>'ID',
            'contextClass'=>fakeCategory::class,
            'indexField'=>false,
            'conditions'=>['true'=>'true'],
            'order'=>false,
        ],$x);

        // context-child
        $x = fakeCategory::initRelationship('label', [
            'type'=>'context-child'
        ]);
        $this->assertEquals([
            'type' => 'context-child',
            'local'=>'ID',
            'contextClass'=>fakeCategory::class,
            'indexField'=>false,
            'conditions'=>[],
            'order'=>[
                'ID'=>'DESC',
            ],
        ],$x);
        $x = fakeCategory::initRelationship('label', [
            'type' => 'context-child',
            'local'=>'ID',
            'contextClass'=>fakeCanary::class,
            'indexField'=>true,
            'conditions'=>[],
            'order'=>[
                'ID'=>'ASC',
            ]
        ]);
        $this->assertEquals([
            'type' => 'context-child',
            'local'=>'ID',
            'contextClass'=>fakeCanary::class,
            'indexField'=>true,
            'conditions'=>[],
            'order'=>[
                'ID'=>'ASC',
            ],
        ],$x);


        // context-parent
        $x = fakeCategory::initRelationship('label', [
            'type'=>'context-parent'
        ]);
        $this->assertEquals([
            'type' => 'context-parent',
            'local'=>'ContextID',
            'foreign'=>'ID',
            'classField'=>'ContextClass',
            'allowedClasses'=>null
        ],$x);

        $x = fakeCategory::initRelationship('label', [
            'type' => 'context-parent',
            'local'=>'ContextID',
            'foreign'=>'ID',
            'classField'=>'ContextClass',
            'allowedClasses'=>[
                fakeCanary::class,
                fakeCategory::class
            ]
        ]);
        $this->assertEquals([
            'type' => 'context-parent',
            'local'=>'ContextID',
            'foreign'=>'ID',
            'classField'=>'ContextClass',
            'allowedClasses'=>[
                fakeCanary::class,
                fakeCategory::class
            ]
        ],$x);
        
    }

    public function testHistoryRelationshipType()
    {
        
        $Canary = relationalCanary::getByID(1);
        
        $expected = relationalCanary::getRevisionsByID($Canary->ID,[
            'order' => [
                'RevisionID' => 'DESC',
            ]
        ]);

        $this->assertEquals($expected,$Canary->History);
    }

    public function testRecursiveHistoryRelationshipType()
    {
        
        
        
        $expected = relationalCanary::getRevisionsByID(20,[
            'order' => [
                'RevisionID' => 'DESC',
            ]
        ]);


        dump($expected->History);
        //$this->assertEquals($expected,$Canary->History);
    }
}
