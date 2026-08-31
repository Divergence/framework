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

use stdClass;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Collections\RecordCollection;
use Divergence\Models\Collections\IndexedRecordField;
use Divergence\Models\Expr\Criteria;
use Divergence\Models\Expr\CriteriaType;
use Divergence\Tests\MockSite\Models\FixtureItem;

class RecordCollectionTest extends TestCase
{
    private RecordCollection $Collection;

    protected function setUp(): void
    {
        $this->Collection = new RecordCollection(static::buildRecords(), ['Team', 'Score'], FixtureItem::class);
    }

    /**
     * @return array<int, FixtureItem>
     */
    private static function buildRecords(): array
    {
        $records = [];

        for ($i = 1; $i <= 20; $i++) {
            $records[] = new FixtureItem([
                'ID' => $i,
                'Name' => sprintf('Item %02d', $i),
                'Team' => $i % 4,
                'Score' => $i * 10,
            ], false, false);
        }

        return $records;
    }

    public function testConstructorInfersRecordClassNameFromFirstRecord(): void
    {
        $collection = new RecordCollection(static::buildRecords());

        $this->assertSame(FixtureItem::class, $collection->recordClassName);
    }

    public function testValidateInfersRecordClassNameWhenUnset(): void
    {
        $collection = new RecordCollection();
        $item = new FixtureItem(['ID' => 1, 'Name' => 'Solo', 'Team' => 0, 'Score' => 10], false, false);

        $collection->add($item);

        $this->assertSame(FixtureItem::class, $collection->recordClassName);
        $this->assertCount(1, $collection);
    }

    public function testValidateRejectsRecordsOfTheWrongClass(): void
    {
        $collection = new RecordCollection([], [], FixtureItem::class);

        $collection->add(new stdClass());

        $this->assertCount(0, $collection);
    }

    public function testGetByFieldReturnsMatchingRecord(): void
    {
        $found = $this->Collection->getByField('Score', 100);

        $this->assertInstanceOf(FixtureItem::class, $found);
        $this->assertSame(10, $found->ID);
    }

    public function testGetAllByFieldReturnsMatchingRecords(): void
    {
        $matches = $this->Collection->getAllByField('Team', 1);

        $this->assertInstanceOf(RecordCollection::class, $matches);
        $this->assertCount(5, $matches);
    }

    public function testGetAllByCriteriaReturnsMatchingRecords(): void
    {
        $matches = $this->Collection->getAllByCriteria(new Criteria('Team', 2, CriteriaType::Equal));

        $this->assertIsArray($matches);
        $this->assertCount(5, $matches);
        $this->assertInstanceOf(FixtureItem::class, $matches[0]);
    }

    public function testUpdateIndexForModelDirectCall(): void
    {
        $item = $this->Collection[0];
        $item->Team = 99;

        $this->Collection->updateIndexForModel('Team', $item);

        $this->assertSame($item, $this->Collection->getByField('Team', 99));
    }

    public function testClearIndexesDirectCall(): void
    {
        $item = $this->Collection[0];

        $this->Collection->clearIndexes($item);

        $this->assertNull($this->Collection->getByField('Score', $item->Score));
    }

    public function testCurrentReturnsActiveRecordInstance(): void
    {
        $this->Collection->rewind();

        $this->assertInstanceOf(FixtureItem::class, $this->Collection->current());
    }

    public function testOffsetGetNegativeIndex(): void
    {
        $this->assertSame($this->Collection[19], $this->Collection[-1]);
    }

    public function testOffsetUnsetRemovesRecord(): void
    {
        $item = $this->Collection[0];

        unset($this->Collection[0]);

        $this->assertCount(19, $this->Collection);
        $this->assertSame(2, $this->Collection[0]->ID);
        $this->assertNull($this->Collection->getByField('Score', $item->Score));
    }

    public function testRemoveDeletesRecordAndClearsIndex(): void
    {
        $item = $this->Collection[0];

        $this->Collection->remove($item);

        $this->assertCount(19, $this->Collection);
        $this->assertNull($this->Collection->getByField('Score', $item->Score));
    }

    public function testRemoveManyDeletesMultipleRecords(): void
    {
        $items = [$this->Collection[0], $this->Collection[1]];

        $this->Collection->removeMany($items);

        $this->assertCount(18, $this->Collection);
    }

    public function testRemoveDecrementsPositionWhenRemovingRecordBeforeCurrentPosition(): void
    {
        $this->Collection->rewind();
        $this->Collection->next();
        $this->Collection->next();
        $this->Collection->next();

        $item = $this->Collection[0];
        $this->Collection->remove($item);

        $this->assertSame(2, $this->Collection->key());
    }

    public function testIndexedRecordFieldIndexableValueHandlesDateStringType(): void
    {
        $index = new IndexedRecordField('CreatedAt', 'DateString');

        $this->assertSame(strtotime('2024-01-01'), $index->indexableValue('2024-01-01'));
    }

    public function testIndexedRecordFieldIndexableValueCastsFloatToString(): void
    {
        $index = new IndexedRecordField('Score');

        $this->assertSame((string) 1.5, $index->indexableValue(1.5));
    }
}
