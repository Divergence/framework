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
use Divergence\Models\ActiveRecord;
use Divergence\IO\Database\MySQL as DB;


use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;

class VersioningTest extends TestCase
{
    public static $table;
    public function setUp(): void
    {
        static::$table = Canary::$historyTable ? Canary::$historyTable : static::$table;
        if (static::$table) {
            Canary::$historyTable = static::$table;
        }
    }

    /**
     *
     */
    public function testGetHistoryTable()
    {
        $this->assertEquals(Canary::$historyTable, Canary::getHistoryTable());
        Canary::$historyTable = null;
        $this->expectExceptionMessage('Static variable $historyTable must be defined to use model versioning.');
        Canary::getHistoryTable();
    }

    /**
     *
     */
    public function testGetRevisionsByID()
    {
        TestUtils::requireDB($this);

        $data = Canary::mock();
        $data['Name'] = uniqid('Versioned-', true);
        $data['Handle'] = uniqid('versioned-', true);
        $Canary = Canary::create($data, true);
        $Canary->Name = uniqid('Version2-', true);
        $Canary->Handle = uniqid('version2-', true);
        $Canary->save();

        $versions = Canary::getRevisionsByID($Canary->ID);

        $this->assertCount(2, $versions);
    }

    /**
     *
     */
    public function testGetRevisions()
    {
        TestUtils::requireDB($this);
        $versions = Canary::getRevisions();

        $this->assertEquals(DB::oneValue('SELECT COUNT(*) FROM '.Canary::getHistoryTable()), count($versions));
        $this->assertInstanceOf(Canary::class, $versions[0]);
    }

    /**
     *
     *
     */
    public function testGetRevisionRecords()
    {
        TestUtils::requireDB($this);

        $versions = Canary::getRevisionRecords();

        $count = DB::oneValue('SELECT COUNT(*) FROM '.Canary::getHistoryTable());

        $this->assertCount($count, $versions);

        // order as string
        $x = Canary::getRevisions(['order'=> ['Name'=>'DESC']]);
        $orderedNames = array_map(fn ($record) => $record->Name, $x);
        $expectedNames = $orderedNames;
        rsort($expectedNames);
        $this->assertSame($expectedNames, $orderedNames);
        $this->assertCount($count, $versions);

        // limit
        $x = Canary::getRevisions(['limit'=> 2]);
        $this->assertCount(2, $x);

        // indexField
    }
}
