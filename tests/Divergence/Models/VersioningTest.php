<?php
namespace Divergence\Tests\Models;

use Divergence\Models\Model;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Models\ActiveRecord;
use Divergence\IO\Database\MySQL as DB;


use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;

class VersioningTest extends TestCase
{
    public static $table;
    public function setUp()
    {
        static::$table = Canary::$historyTable ? Canary::$historyTable : static::$table;
        if (static::$table) {
            Canary::$historyTable = static::$table;
        }
    }

    /**
     * @covers Divergence\Models\Versioning::getHistoryTable
     */
    public function testGetHistoryTable()
    {
        $this->assertEquals(Canary::$historyTable, Canary::getHistoryTable());
        Canary::$historyTable = null;
        $this->expectExceptionMessage('Static variable $historyTable must be defined to use model versioning.');
        Canary::getHistoryTable();
    }

    /**
     * @covers Divergence\Models\Versioning::getRevisionsByID
     */
    public function testGetRevisionsByID()
    {
        TestUtils::requireDB($this);

        $Canary = Canary::getByField('Name', 'Version2');
        $versions = Canary::getRevisionsByID($Canary->ID);

        $this->assertCount(2, $versions);
    }

    /**
     * @covers Divergence\Models\Versioning::getRevisions
     */
    public function testGetRevisions()
    {
        TestUtils::requireDB($this);
        $Canary = Canary::getByField('Name', 'Version2');
        $versions = Canary::getRevisions($Canary->ID);

        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM '.Canary::getHistoryTable()), count($versions));
        $this->assertInstanceOf(Canary::class, $versions[0]);
    }

    /**
     * @covers Divergence\Models\Versioning::getRevisionRecords
     */
    public function testGetRevisionRecords()
    {
        TestUtils::requireDB($this);

        $Canary = Canary::getByField('Name', 'Version2');
        $versions = Canary::getRevisionRecords($Canary->ID);

        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM '.Canary::getHistoryTable()), count($versions));
    }
}
