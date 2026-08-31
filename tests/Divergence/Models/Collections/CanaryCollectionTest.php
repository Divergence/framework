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

use PHPUnit\Framework\TestCase;
use Divergence\Models\Collections\IndexedRecordField;
use Divergence\Models\Collections\RecordCollection;
use Divergence\Models\Expr\Conjunction;
use Divergence\Models\Expr\Criteria;
use Divergence\Models\Expr\CriteriaGroup;
use Divergence\Tests\TestUtils;
use Divergence\Tests\MockSite\Collections\CanaryCollection;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Models\IndexedCanary;
use Divergence\Tests\MockSite\Models\Tag;

class CanaryCollectionTest extends TestCase
{
    private const FIELD_TYPES = [
        'ID' => 'integer',
        'Class' => 'enum',
        'Created' => 'timestamp',
        'CreatorID' => 'integer',
        'ContextID' => 'int',
        'ContextClass' => 'enum',
        'DNA' => 'clob',
        'Name' => 'string',
        'Handle' => 'string',
        'isAlive' => 'boolean',
        'DNAHash' => 'password',
        'StatusCheckedLast' => 'timestamp',
        'SerializedData' => 'serialized',
        'Colors' => 'set',
        'EyeColors' => 'list',
        'Height' => 'float',
        'LongestFlightTime' => 'int',
        'HighestRecordedAltitude' => 'uint',
        'ObservationCount' => 'integer',
        'DateOfBirth' => 'date',
        'Weight' => 'decimal',
        'RevisionID' => 'integer',
    ];

    private CanaryCollection $Collection;

    protected function setUp(): void
    {
        $this->Collection = new CanaryCollection([
            new Canary(static::record(1), false, false),
            new Canary(static::record(2), false, false),
        ]);
    }

    private static function record(int $id): array
    {
        return [
            'ID' => $id,
            'Class' => Canary::class,
            'Created' => sprintf('2024-01-%02d 03:04:05', $id),
            'CreatorID' => 100 + $id,
            'ContextID' => 200 + $id,
            'ContextClass' => Tag::class,
            'DNA' => str_repeat($id === 1 ? 'ATGC' : 'CGTA', 250),
            'Name' => sprintf('Canary %d', $id),
            'Handle' => sprintf('canary-%d', $id),
            'isAlive' => $id === 1,
            'DNAHash' => hash('sha256', sprintf('canary-%d', $id)),
            'StatusCheckedLast' => sprintf('2024-02-%02d 04:05:06', $id),
            'SerializedData' => serialize(['canary' => $id, 'nested' => ['alive' => $id === 1]]),
            'Colors' => $id === 1 ? ['red', 'purple'] : ['blue', 'green'],
            'EyeColors' => $id === 1 ? ['amber', 'brown'] : ['cyan', 'teal'],
            'Height' => 10.5 + $id,
            'LongestFlightTime' => 1000 + $id,
            'HighestRecordedAltitude' => 2000 + $id,
            'ObservationCount' => 3000 + $id,
            'DateOfBirth' => sprintf('2020-03-%02d', $id),
            'Weight' => sprintf('1%d.2%d', $id, $id),
            'RevisionID' => 4000 + $id,
        ];
    }

    public function testIndexesEveryCanaryField(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(Canary::getClassFields()),
            array_keys(static::FIELD_TYPES)
        );
        $this->assertEqualsCanonicalizing(
            array_keys(static::FIELD_TYPES),
            array_keys($this->Collection->Indexes)
        );
    }

    public function testIndexedCanaryAttributeCreatesCollectionWithEveryFieldIndex(): void
    {
        TestUtils::requireDB($this);

        $Collection = IndexedCanary::getAll(['order' => ['ID' => 'ASC']]);

        $this->assertInstanceOf(RecordCollection::class, $Collection);
        $this->assertSame(IndexedCanary::class, $Collection->recordClassName);
        $this->assertNotEmpty($Collection);
        $this->assertEqualsCanonicalizing(
            array_keys(IndexedCanary::getClassFields()),
            array_keys($Collection->Indexes)
        );

        $Canary = $Collection[0];

        foreach (static::FIELD_TYPES as $field => $type) {
            $this->assertInstanceOf(IndexedRecordField::class, $Collection->Indexes[$field]);
            $this->assertSame($type, $Collection->Indexes[$field]->type);
            $this->assertSame($Canary, $Collection->getByField($field, $Canary->getValue($field)));
        }
    }

    public function testIndexedCanaryAttributeReturnsFreshCollections(): void
    {
        TestUtils::requireDB($this);

        $FirstCollection = IndexedCanary::getAll(['limit' => 1]);
        $SecondCollection = IndexedCanary::getAll(['limit' => 1]);

        $this->assertNotSame($FirstCollection, $SecondCollection);
        $this->assertNotSame($FirstCollection[0], $SecondCollection[0]);
        $this->assertSame($FirstCollection[0]->ID, $SecondCollection[0]->ID);
    }

    public function testIndexedCanaryOrCriteriaReturnsKnownLiveRecords(): void
    {
        TestUtils::requireDB($this);

        $Collection = IndexedCanary::getAll(['order' => ['ID' => 'ASC']]);

        $this->assertGreaterThanOrEqual(2, count($Collection));

        $FirstCanary = $Collection[0];
        $SecondCanary = $Collection[1];

        $this->assertNotSame($FirstCanary->ID, $SecondCanary->ID);
        $this->assertNotSame($FirstCanary->Handle, $SecondCanary->Handle);

        $matches = $Collection->getAllByCriteria(new CriteriaGroup([
            new Criteria('ID', $FirstCanary->ID),
            new Criteria('Handle', $SecondCanary->Handle),
        ], Conjunction::GroupOr));

        $this->assertSame([$FirstCanary, $SecondCanary], $matches);
    }

    public function testIndexedCanaryNotOrCriteriaReturnsKnownLiveComplement(): void
    {
        TestUtils::requireDB($this);

        $Collection = IndexedCanary::getAll(['order' => ['ID' => 'ASC']]);

        $this->assertGreaterThanOrEqual(2, count($Collection));

        $matches = $Collection->getAllByCriteria(new CriteriaGroup([
            new Criteria('ID', $Collection[0]->ID),
            new Criteria('Handle', $Collection[1]->Handle),
        ], Conjunction::GroupNotOr));

        $this->assertSame(array_slice($Collection->toArray(), 2), $matches);
    }

    public function testIndexedCanaryAttributeReturnsIndexedEmptyOrmResult(): void
    {
        TestUtils::requireDB($this);

        $Collection = IndexedCanary::getAll();

        $this->assertNotEmpty($Collection);

        $IDs = array_map(function ($Canary) {
            return $Canary->ID;
        }, $Collection->toArray());
        $EmptyCollection = IndexedCanary::getAllByWhere([
            'ID' => max($IDs) + 1,
        ]);

        $this->assertInstanceOf(RecordCollection::class, $EmptyCollection);
        $this->assertSame(IndexedCanary::class, $EmptyCollection->recordClassName);
        $this->assertCount(0, $EmptyCollection);
        $this->assertEqualsCanonicalizing(
            array_keys(IndexedCanary::getClassFields()),
            array_keys($EmptyCollection->Indexes)
        );
    }

    public function testCanaryWithoutAttributeStillReturnsArray(): void
    {
        TestUtils::requireDB($this);

        $records = Canary::getAll(['limit' => 1]);

        $this->assertIsArray($records);
        $this->assertInstanceOf(Canary::class, $records[0]);
    }

    public function testEveryCanaryFieldTypeCanBeIndexed(): void
    {
        $Canary = $this->Collection[0];

        foreach (static::FIELD_TYPES as $field => $type) {
            $this->assertInstanceOf(IndexedRecordField::class, $this->Collection->Indexes[$field]);
            $this->assertSame($type, $this->Collection->Indexes[$field]->type);
            $this->assertSame($Canary, $this->Collection->getByField($field, $Canary->getValue($field)));
        }
    }
}
