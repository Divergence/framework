<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Models;

use Divergence\Models\Model;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Models\ActiveRecord;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\MockSite\Models\Forum\Post;
use Divergence\Tests\Models\Testables\fakeCanary;
use Divergence\Tests\MockSite\Models\Forum\Thread;

use Divergence\Tests\Models\Testables\fakeCategory;
use Divergence\Tests\MockSite\Models\Forum\Category;
use Divergence\Tests\Models\Testables\relationalTag;
use Divergence\Tests\Models\Testables\relationalCanary;

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
            'type'=>'context-children',
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
        ], $x);

        // context-parent
        $x = fakeCategory::initRelationship('label', [
            'type'=>'context-parent',
        ]);
        $this->assertEquals([
            'type' => 'context-parent',
            'local'=>'ContextID',
            'foreign'=>'ID',
            'classField'=>'ContextClass',
            'allowedClasses'=>null,
        ], $x);

        $x = fakeCategory::initRelationship('label', [
            'type' => 'context-parent',
            'local'=>'ContextID',
            'foreign'=>'ID',
            'classField'=>'ContextClass',
            'allowedClasses'=>[
                fakeCanary::class,
                fakeCategory::class,
            ],
        ]);
        $this->assertEquals([
            'type' => 'context-parent',
            'local'=>'ContextID',
            'foreign'=>'ID',
            'classField'=>'ContextClass',
            'allowedClasses'=>[
                fakeCanary::class,
                fakeCategory::class,
            ],
        ], $x);
    }

    public function testInitRelationshipManyMany()
    {
        $x = fakeCategory::initRelationship('label', [
            'type' => 'many-many',
            'class' => fakeCanary::class,
            'linkClass' => 'linkyClass',
        ]);

        $this->assertEquals([
            'type' => 'many-many',
            'class' => fakeCanary::class,
            'linkClass' => 'linkyClass',
            'linkLocal' => 'CategoryID',
            'linkForeign' => 'CanaryID',
            'local' => 'ID',
            'foreign' => 'ID',
            'indexField' => false,
            'conditions' => [],
            'order' => false,
        ], $x);
    }

    public function testInitRelationshipManyManyExceptionMissingClass()
    {
        $this->expectExceptionMessage('Relationship type many-many option requires a class setting.');
        $x = fakeCategory::initRelationship('label', [
            'type' => 'many-many',
        ]);
    }

    public function testInitRelationshipManyManyExceptionLinkClass()
    {
        $this->expectExceptionMessage('Relationship type many-many option requires a linkClass setting.');
        $x = fakeCategory::initRelationship('label', [
            'type' => 'many-many',
            'class' => 'anything',
        ]);
    }

    public function testHistoryRelationshipType()
    {
        $Canary = relationalCanary::getByID(1);

        $expected = relationalCanary::getRevisionsByID($Canary->ID, [
            'order' => [
                'RevisionID' => 'DESC',
            ],
        ]);

        $this->assertEquals($expected, $Canary->History);
    }

    public function testRecursiveHistoryRelationshipType()
    {
        $expected = relationalCanary::getRevisionsByID(20, [
            'order' => [
                'RevisionID' => 'DESC',
            ],
        ]);

        $History = $expected[0]->History;
        for ($i=0;$i<count($History);$i++) {
            $this->assertEquals($expected[$i]->data, $History[$i]->data);
        }
    }

    public function testContextParentRelationship()
    {
        $x = relationalCanary::getByID(1);
        $class = $x->ContextClass;
        $y = $class::getByID($x->ContextID);
        $this->assertEquals($x->ContextParent, $y);
    }

    public function testContextChildrenRelationship()
    {
        $x = relationalTag::getByID(7);
        $this->assertEquals(count($x->ContextChildren), DB::oneValue('SELECT COUNT(*) FROM `canaries`'));
    }
}
