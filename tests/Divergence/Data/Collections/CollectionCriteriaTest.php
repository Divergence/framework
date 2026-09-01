<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Data\Collections;

use stdClass;
use Error;
use RuntimeException;
use Exception;
use PHPUnit\Framework\TestCase;
use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\IndexedField;
use Divergence\Data\Collections\Factory\Factory;
use Divergence\Data\Collections\Factory\Getters\GetByField;
use Divergence\Models\Expr\Criteria;
use Divergence\Models\Expr\CriteriaGroup;
use Divergence\Models\Expr\CriteriaType;
use Divergence\Models\Expr\Conjunction;

class CollectionCriteriaTest extends TestCase
{
    private Collection $Collection;

    protected function setUp(): void
    {
        $this->Collection = (new Factory())->create(static::buildRecords(), ['Status', 'Team', 'Score']);
    }

    /**
     * @return array<int, stdClass>
     */
    private static function buildRecords(): array
    {
        $records = [];

        for ($i = 1; $i <= 100; $i++) {
            $record = new stdClass();
            $record->ID = $i;
            $record->Name = sprintf('Record %03d', $i);
            $record->Status = $i % 4;
            $record->Team = $i % 10;
            $record->Score = $i * 10;
            $record->ScoreCopy = $i * 10;
            $record->Threshold = 500;
            $record->Tag = ['alpha', 'beta', 'gamma', 'delta'][$i % 4];
            $record->Note = ($i % 5 === 0) ? null : sprintf('note-%d', $i);
            $records[] = $record;
        }

        return $records;
    }

    private function assertOperatorCount(int $operator, string $field, $value, int $expectedCount): void
    {
        $matches = $this->Collection->getAllByCriteria(new Criteria($field, $value, $operator));

        $this->assertCount($expectedCount, $matches);
    }

    public function testEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::Equal, 'Score', 500, 1);
    }

    public function testNotEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::NotEqual, 'Team', 0, 90);
    }

    public function testGreaterThanOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::GreaterThan, 'Score', 950, 5);
    }

    public function testGreaterThanOrEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::GreaterThanOrEqual, 'Score', 950, 6);
    }

    public function testLessThanOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::LessThan, 'Score', 50, 4);
    }

    public function testLessThanOrEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::LessThanOrEqual, 'Score', 50, 5);
    }

    public function testLikeOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::Like, 'Tag', 'al%', 25);
    }

    public function testNotLikeOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::NotLike, 'Tag', 'al%', 75);
    }

    public function testInOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::In, 'Team', [1, 2, 3], 30);
    }

    public function testNotInOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::NotIn, 'Team', [1, 2, 3], 70);
    }

    public function testNulledOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::Nulled, 'Note', null, 20);
    }

    public function testNotNulledOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::NotNulled, 'Note', null, 80);
    }

    public function testFieldEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::FieldEqual, 'Score', 'ScoreCopy', 100);
    }

    public function testFieldNotEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::FieldNotEqual, 'Score', 'Threshold', 99);
    }

    public function testFieldGreaterThanOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::FieldGreaterThan, 'Score', 'Threshold', 50);
    }

    public function testFieldGreaterThanOrEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::FieldGreaterThanOrEqual, 'Score', 'Threshold', 51);
    }

    public function testFieldLessThanOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::FieldLessThan, 'Score', 'Threshold', 49);
    }

    public function testFieldLessThanOrEqualOperator(): void
    {
        $this->assertOperatorCount(CriteriaType::FieldLessThanOrEqual, 'Score', 'Threshold', 50);
    }

    public function testGetByCriteriaReturnsFirstMatch(): void
    {
        $found = $this->Collection->getByCriteria(new Criteria('Score', 500, CriteriaType::Equal));

        $this->assertNotNull($found);
        $this->assertSame('Record 050', $found->Name);
    }

    public function testGetByCriteriaReturnsNullWhenNoMatch(): void
    {
        $found = $this->Collection->getByCriteria(new Criteria('Score', 999999, CriteriaType::Equal));

        $this->assertNull($found);
    }

    public function testNestedAndOr(): void
    {
        $tree = new CriteriaGroup([
            new CriteriaGroup([
                new Criteria('Team', 2, CriteriaType::Equal),
                new Criteria('Score', 20, CriteriaType::Equal),
            ], Conjunction::GroupAnd),
            new CriteriaGroup([
                new Criteria('Team', 0, CriteriaType::Equal),
                new Criteria('Score', 1000, CriteriaType::Equal),
            ], Conjunction::GroupAnd),
        ], Conjunction::GroupOr);

        $names = [];
        foreach ($this->Collection->getAllByCriteria($tree) as $record) {
            $names[] = $record->Name;
        }
        sort($names);

        $this->assertSame(['Record 002', 'Record 100'], $names);
    }

    public function testNotAndIsNegationOfAnd(): void
    {
        $and = new CriteriaGroup([
            new Criteria('Team', 1, CriteriaType::Equal),
            new Criteria('Status', 1, CriteriaType::Equal),
        ], Conjunction::GroupAnd);

        $notAnd = new CriteriaGroup($and->criteria, Conjunction::GroupNotAnd);

        $andCount = count($this->Collection->getAllByCriteria($and));
        $notAndCount = count($this->Collection->getAllByCriteria($notAnd));

        $this->assertSame(100, $andCount + $notAndCount);
    }

    public function testNotOrIsNegationOfOr(): void
    {
        $or = new CriteriaGroup([
            new Criteria('Team', 1, CriteriaType::Equal),
            new Criteria('Status', 1, CriteriaType::Equal),
        ], Conjunction::GroupOr);

        $notOr = new CriteriaGroup($or->criteria, Conjunction::GroupNotOr);

        $orCount = count($this->Collection->getAllByCriteria($or));
        $notOrCount = count($this->Collection->getAllByCriteria($notOr));

        $this->assertSame(100, $orCount + $notOrCount);
    }

    public function testSingleCriterionNotAndGroupReturnsComplement(): void
    {
        $matches = $this->Collection->getAllByCriteria(new CriteriaGroup([
            new Criteria('Team', 1, CriteriaType::Equal),
        ], Conjunction::GroupNotAnd));

        $this->assertCount(90, $matches);
    }

    public function testSingleCriterionNotOrGroupReturnsComplement(): void
    {
        $matches = $this->Collection->getAllByCriteria(new CriteriaGroup([
            new Criteria('Team', 1, CriteriaType::Equal),
        ], Conjunction::GroupNotOr));

        $this->assertCount(90, $matches);
    }

    public function testEmptyAndGroupMatchesNothing(): void
    {
        $matches = $this->Collection->getAllByCriteria(new CriteriaGroup([], Conjunction::GroupAnd));

        $this->assertCount(0, $matches);
    }

    public function testEmptyOrGroupMatchesNothing(): void
    {
        $matches = $this->Collection->getAllByCriteria(new CriteriaGroup([], Conjunction::GroupOr));

        $this->assertCount(0, $matches);
    }

    public function testGetAllByCriteriaWithAndGroup(): void
    {
        $matches = $this->Collection->getAllByCriteria(new CriteriaGroup([
            new Criteria('Team', 1, CriteriaType::Equal),
            new Criteria('Status', 1, CriteriaType::Equal),
        ], Conjunction::GroupAnd));

        $this->assertCount(5, $matches);
        $this->assertSame('Record 001', $matches[0]->Name);
    }

    public function testGetAllByCriteriaReturnsArray(): void
    {
        $matches = $this->Collection->getAllByCriteria(new Criteria('Team', 1, CriteriaType::Equal));

        $this->assertIsArray($matches);
        $this->assertCount(10, $matches);
    }

    public function testValidateAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->Collection->validate('anything'));
        $this->assertTrue($this->Collection->validate(null));
    }

    public function testRemoveDeletesRecordAndClearsIndex(): void
    {
        $record = $this->Collection[0];

        $this->Collection->remove($record);

        $this->assertCount(99, $this->Collection);
        $this->assertNull($this->Collection->getByField('Score', $record->Score));
    }

    public function testRemoveManyDeletesMultipleRecords(): void
    {
        $records = [$this->Collection[0], $this->Collection[1], $this->Collection[2]];

        $this->Collection->removeMany($records);

        $this->assertCount(97, $this->Collection);
    }

    public function testToArrayReturnsIndexArray(): void
    {
        $array = $this->Collection->toArray();

        $this->assertIsArray($array);
        $this->assertCount(100, $array);
        $this->assertSame($this->Collection->Index, $array);
    }

    public function testHasIndexReturnsTrueForConfiguredFieldAndFalseOtherwise(): void
    {
        $this->assertTrue($this->Collection->hasIndex('Team'));
        $this->assertFalse($this->Collection->hasIndex('NotAnIndexedField'));
    }

    public function testUpdateIndexForModelDirectCall(): void
    {
        $record = $this->Collection[0];
        $record->Team = 99;

        $this->Collection->updateIndexForModel('Team', $record);

        $this->assertSame($record, $this->Collection->getByField('Team', 99));
    }

    public function testClearIndexesDirectCall(): void
    {
        $record = $this->Collection[0];

        $this->Collection->clearIndexes($record);

        $this->assertNull($this->Collection->getByField('Score', $record->Score));
    }

    public function testKeyReturnsCurrentPosition(): void
    {
        $this->Collection->rewind();
        $this->assertSame(0, $this->Collection->key());

        $this->Collection->next();
        $this->assertSame(1, $this->Collection->key());
    }

    public function testOffsetSetAddsRecordWithNullOffset(): void
    {
        $record = new stdClass();
        $record->ID = 101;
        $record->Status = 0;
        $record->Team = 0;
        $record->Score = 1234;

        $this->Collection[] = $record;

        $this->assertCount(101, $this->Collection);
        $this->assertSame($record, $this->Collection[100]);
    }

    public function testDuplicateIdentityDoesNotDivergeCollectionAndIndexCounts(): void
    {
        $record = new stdClass();
        $record->Status = 1;
        $collection = (new Factory())->create([$record], ['Status']);

        $collection->add($record);

        $this->assertCount($collection->count(), $collection->getAllByField('Status', 1));
    }

    public function testObjectRecordsHaveDistinctIdentities(): void
    {
        $first = new stdClass();
        $first->ID = 1;
        $first->Status = 1;

        $second = new stdClass();
        $second->ID = 2;
        $second->Status = 1;

        $collection = (new Factory())->create([
            $first,
            $second,
        ], ['Status']);

        $this->assertCount(2, $collection);
        $this->assertCount(2, $collection->getAllByField('Status', 1));
    }

    public function testOffsetSetReplacesRecordAtExistingOffset(): void
    {
        $original = $this->Collection[0];
        $replacement = new stdClass();
        $replacement->ID = $original->ID;
        $replacement->Status = $original->Status;
        $replacement->Team = $original->Team;
        $replacement->Score = 98765;

        $this->Collection[0] = $replacement;

        $this->assertSame($replacement, $this->Collection[0]);
        $this->assertNull($this->Collection->getByField('Score', $original->Score));
        $this->assertSame($replacement, $this->Collection->getByField('Score', 98765));
    }

    public function testOffsetSetRemovesReplacedRecordFromHashKeyIndex(): void
    {
        $original = $this->Collection[0];
        $replacement = clone $original;

        $this->Collection[0] = $replacement;

        $this->assertArrayNotHasKey(spl_object_id($original), $this->Collection->HashKeyIndex);
    }

    public function testOffsetExists(): void
    {
        $this->assertTrue(isset($this->Collection[0]));
        $this->assertFalse(isset($this->Collection[999]));
    }

    public function testOffsetUnset(): void
    {
        unset($this->Collection[0]);

        $this->assertCount(99, $this->Collection);
        $this->assertSame(2, $this->Collection[0]->ID);
    }

    public function testOffsetUnsetKeepsIterationAndNegativeOffsetsConsistent(): void
    {
        $last = $this->Collection[-1];

        unset($this->Collection[0]);

        $this->assertSame(
            [99, $last],
            [count(iterator_to_array($this->Collection, false)), $this->Collection[-1]]
        );
    }

    public function testOffsetGetNegativeIndex(): void
    {
        $this->assertSame($this->Collection[99], $this->Collection[-1]);
    }

    public function testGetterMagicCallThrowsForUndefinedMethod(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage(sprintf(
            'Call to undefined method %s::bogusGetterMethod()',
            Collection::class
        ));

        $this->Collection->bogusGetterMethod();
    }

    public function testGetterMagicCallStaticDelegatesToFactory(): void
    {
        $fresh = Collection::create([], []);

        $this->assertInstanceOf(Collection::class, $fresh);
        $this->assertNotSame($this->Collection, $fresh);
    }

    public function testGetterMagicCallStaticThrowsForUndefinedMethod(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage(sprintf(
            'Call to undefined method %s::bogusStaticMethod()',
            Collection::class
        ));

        Collection::bogusStaticMethod();
    }

    public function testCriteriaRawOperatorDoesNotOverrideTypedOperator(): void
    {
        $criteria = new Criteria('Score', 500);
        $criteria->rawOperator = '= 500';

        $matches = $this->Collection->getAllByCriteria($criteria);

        $this->assertCount(1, $matches);
        $this->assertSame('Record 050', $matches[0]->Name);
    }

    public function testUnsupportedCriteriaOperatorCannotBeEvaluatedInMemory(): void
    {
        $this->expectException(RuntimeException::class);

        $this->Collection->getAllByCriteria(new Criteria('Score', 500, CriteriaType::Raw));
    }

    public function testFactoryThrowsOnGetterMethodCollision(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Getter method collision for getbyfield');

        new class extends Factory {
            protected function registerGetterClasses(): void
            {
                $this->registerGetterClass(GetByField::class);
                $this->registerGetterClass(GetByField::class);
            }
        };
    }

    public function testCreateIndexByFieldRebuildsIndexFromExistingRecords(): void
    {
        $this->Collection->createIndexByField('ID');

        $this->assertTrue($this->Collection->hasIndex('ID'));
        $this->assertSame(50, $this->Collection->getByField('ID', 50)->ID);
    }

    public function testGetByFieldReturnsNullWhenValueNotIndexed(): void
    {
        $this->assertNull($this->Collection->getByField('Team', 99999));
    }

    public function testGetByFieldReturnsNullForUnindexedField(): void
    {
        $this->assertNull($this->Collection->getByField('NotAnIndexedField', 'anything'));
    }

    public function testGetAllByFieldReturnsMatchingRecords(): void
    {
        $matches = $this->Collection->getAllByField('Team', 5);

        $this->assertInstanceOf(Collection::class, $matches);
        $this->assertCount(10, $matches);
    }

    public function testIndexedFieldIndexableValueHandlesDateStringType(): void
    {
        $index = new IndexedField('CreatedAt', 'DateString');

        $this->assertSame(strtotime('2024-01-01'), $index->indexableValue('2024-01-01'));
    }

    private function buildTimestampIndex(): IndexedField
    {
        $first = new stdClass();
        $first->CreatedAt = '2024-01-01 00:00:00';
        $second = new stdClass();
        $second->CreatedAt = '2024-01-02 00:00:00';
        $records = [$first, $second];
        $index = new IndexedField('CreatedAt', 'timestamp');
        $index->rebuildIndex($records);

        return $index;
    }

    public function testTimestampInOperatorAcceptsArrays(): void
    {
        $matches = $this->buildTimestampIndex()->find(
            ['2024-01-01 00:00:00'],
            CriteriaType::In
        );

        $this->assertCount(1, $matches);
    }

    public function testTimestampNotInOperatorAcceptsArrays(): void
    {
        $matches = $this->buildTimestampIndex()->find(
            ['2024-01-01 00:00:00'],
            CriteriaType::NotIn
        );

        $this->assertCount(1, $matches);
    }

    public function testIndexedFieldFindDefaultsToAnEmptyArray(): void
    {
        $this->assertSame([], (new IndexedField('Value'))->find());
    }

    public function testIndexedFieldFindReturnsAnEmptyArrayWhenNothingMatches(): void
    {
        $this->assertSame([], $this->buildTimestampIndex()->find('2030-01-01 00:00:00'));
    }

    public function testTimestampIndexNormalizesUnixEpoch(): void
    {
        $index = new IndexedField('CreatedAt', 'timestamp');

        $this->assertSame(0, $index->indexableValue('1970-01-01 00:00:00 UTC'));
    }

    public function testIndexedFieldRemovesUnusedCardinalities(): void
    {
        $index = new class('Status') extends IndexedField {
            public function countCardinalities(): int
            {
                return count($this->cardinality);
            }
        };
        $record = new stdClass();
        $record->Status = 'first';
        $index->set($record);

        $record->Status = 'second';
        $index->set($record);

        $this->assertSame(1, $index->countCardinalities());
    }

    public function testIndexedFieldIndexableValueCastsFloatToString(): void
    {
        $index = new IndexedField('Score');

        $this->assertSame((string) 1.5, $index->indexableValue(1.5));
    }

    public function testRemoveDecrementsPositionWhenRemovingRecordBeforeCurrentPosition(): void
    {
        $this->Collection->rewind();
        $this->Collection->next();
        $this->Collection->next();
        $this->Collection->next();

        $record = $this->Collection[0];
        $this->Collection->remove($record);

        $this->assertSame(2, $this->Collection->key());
    }
}
