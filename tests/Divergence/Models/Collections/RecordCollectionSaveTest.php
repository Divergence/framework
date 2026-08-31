<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Models\Collections;

use Exception;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Collections\RecordCollection;
use Divergence\Tests\MockSite\Models\FixtureItem;

class RecordCollectionSaveTest extends TestCase
{
    /**
     * @return array<int, FixtureItem>
     */
    private static function buildPhantomRecords(int $count, string $namePrefix): array
    {
        $records = [];

        for ($i = 1; $i <= $count; $i++) {
            $records[] = new FixtureItem([
                'Name' => sprintf('%s Item %d', $namePrefix, $i),
                'Team' => $i % 2,
                'Score' => $i * 10,
            ], true, true);
        }

        return $records;
    }

    public function testSaveWithTransactionPersistsPhantomRecords(): void
    {
        $collection = new RecordCollection(
            static::buildPhantomRecords(5, 'Transaction Save'),
            [],
            FixtureItem::class
        );

        $collection->saveWithTransaction();

        foreach ($collection as $Model) {
            $this->assertFalse($Model->isPhantom);
            $this->assertIsInt($Model->ID);
            $this->assertSame($Model->Name, FixtureItem::getByID($Model->ID)->Name);
        }
    }

    public function testSaveWithTransactionPropagatesPrimaryKeysAcrossIndexes(): void
    {
        $records = static::buildPhantomRecords(2, 'Transaction Primary Key');
        $collection = new RecordCollection($records, ['Team'], FixtureItem::class);

        $collection->saveWithTransaction();

        foreach ($records as $record) {
            $this->assertNotNull($record->getPrimaryKeyValue());
            $this->assertSame($record, $collection->HashKeyIndex[$record->ID] ?? null);
            $this->assertSame($record->Name, FixtureItem::getByID($record->ID)->Name);
        }

        $this->assertSame([$records[1]], $collection->getAllByField('Team', 0)->toArray());
        $this->assertSame([$records[0]], $collection->getAllByField('Team', 1)->toArray());

        $records[0]->Team = 0;
        $collection->saveWithTransaction();

        $this->assertSame([], $collection->getAllByField('Team', 1)->toArray());
        $this->assertEqualsCanonicalizing($records, $collection->getAllByField('Team', 0)->toArray());
    }

    public function testPhantomRecordsHaveDistinctCollectionIdentities(): void
    {
        $collection = new RecordCollection(
            static::buildPhantomRecords(2, 'Phantom Identity'),
            ['Team'],
            FixtureItem::class
        );

        $this->assertSame([
            2,
            1,
            1,
        ], [
            count($collection->HashKeyIndex),
            count($collection->getAllByField('Team', 0)),
            count($collection->getAllByField('Team', 1)),
        ]);
    }

    public function testSaveRekeysPhantomRecordsByPrimaryKey(): void
    {
        $records = static::buildPhantomRecords(2, 'Phantom Rekey');
        $collection = new RecordCollection($records, ['Team'], FixtureItem::class);

        $collection->save();

        $indexedRecords = [];
        foreach ($records as $record) {
            $indexedRecords[] = $collection->HashKeyIndex[$record->ID] ?? null;
        }

        $this->assertSame($records, $indexedRecords);
    }

    public function testSaveWithTransactionIsNoOpForEmptyCollection(): void
    {
        $recordsBefore = FixtureItem::getAll();
        $collection = new RecordCollection([], [], FixtureItem::class);

        $collection->saveWithTransaction();

        $this->assertCount(count($recordsBefore), FixtureItem::getAll());
    }

    public function testSavePersistsDirtyRecordsWithoutTransaction(): void
    {
        $records = static::buildPhantomRecords(1, 'Save');
        $collection = new RecordCollection($records, [], FixtureItem::class);

        $collection->save();

        $this->assertFalse($collection->isDirty());
        $this->assertSame($records[0]->Name, FixtureItem::getByID($records[0]->ID)->Name);
    }

    public function testIsDirtyReflectsUnsavedRecords(): void
    {
        $collection = new RecordCollection(
            static::buildPhantomRecords(1, 'Dirty'),
            [],
            FixtureItem::class
        );

        $this->assertTrue($collection->isDirty());

        $collection->saveWithTransaction();

        $this->assertFalse($collection->isDirty());
    }

    public function testSaveWithTransactionRollsBackOnFailure(): void
    {
        $duplicateName = 'Rollback Collision';
        $duplicateRecord = new FixtureItem([
            'Name' => $duplicateName,
            'Team' => 0,
            'Score' => 1,
        ], true, true);

        $records = static::buildPhantomRecords(2, 'Rollback');
        $records[] = $duplicateRecord;
        $records[] = new FixtureItem([
            'Name' => $duplicateName,
            'Team' => 1,
            'Score' => 2,
        ], true, true);

        $collection = new RecordCollection($records, [], FixtureItem::class);

        $this->expectException(Exception::class);

        try {
            $collection->saveWithTransaction();
        } finally {
            foreach (['Rollback Item 1', 'Rollback Item 2', $duplicateName] as $name) {
                $this->assertSame([], FixtureItem::getAllByField('Name', $name));
            }
        }
    }

    public function testSaveWithTransactionRestoresCollectionStateAfterRollback(): void
    {
        $duplicateName = 'Rollback State Collision';
        $records = static::buildPhantomRecords(2, 'Rollback State');
        $records[] = new FixtureItem([
            'Name' => $duplicateName,
            'Team' => 0,
            'Score' => 1,
        ], true, true);
        $records[] = new FixtureItem([
            'Name' => $duplicateName,
            'Team' => 1,
            'Score' => 2,
        ], true, true);

        $collection = new RecordCollection($records, ['Team'], FixtureItem::class);
        $recordOrderBefore = $collection->toArray();
        $hashKeysBefore = array_keys($collection->HashKeyIndex);
        $teamZeroBefore = $collection->getAllByField('Team', 0)->toArray();
        $teamOneBefore = $collection->getAllByField('Team', 1)->toArray();
        $exception = null;

        try {
            $collection->saveWithTransaction();
        } catch (Exception $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertSame(
            array_fill(0, count($records), true),
            array_map(function (FixtureItem $record) {
                return $record->isPhantom;
            }, $records)
        );
        $this->assertSame(
            array_fill(0, count($records), null),
            array_map(function (FixtureItem $record) {
                return $record->getPrimaryKeyValue();
            }, $records)
        );
        $this->assertTrue($collection->isDirty());
        $this->assertSame($recordOrderBefore, $collection->toArray());
        $this->assertSame($hashKeysBefore, array_keys($collection->HashKeyIndex));
        $this->assertSame($teamZeroBefore, $collection->getAllByField('Team', 0)->toArray());
        $this->assertSame($teamOneBefore, $collection->getAllByField('Team', 1)->toArray());
    }
}
