<?php
namespace Divergence\Tests\Models;

use Divergence\Models\Model;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Models\ActiveRecord;
use Divergence\IO\Database\MySQL as DB;

use Divergence\Tests\MockSite\Models\Forum\Category;
use Divergence\Tests\MockSite\Models\Forum\Thread;
use Divergence\Models\Versioning;
use Divergence\Models\Relations;

class fakeCategory extends Category
{
    use Versioning, Relations;
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

    public function test__get()
    {
        $Category = Category::getByID(1);
        $Threads = $Category->Threads;
        $Expected = Thread::getAllByField('CategoryID',1);
        $this->assertEquals($Expected,$Threads);
    }
}