<?php

namespace Divergence\Tests\Data\Collections;

use Divergence\Data\Collections\IndexedField;
use PHPUnit\Framework\TestCase;
use stdClass;

class IndexedFieldTest extends TestCase
{
    public function testTrueAndFalseCardinalitiesDoNotCollide(): void
    {
        $this->assertCardinalitiesRemainDistinct([true, false]);
    }

    public function testBooleanCardinalitiesDoNotCollideWithStringValues(): void
    {
        $this->assertCardinalitiesRemainDistinct([true, '1', 'true', false, '0', 'false', '']);
    }

    public function testBooleanCardinalitiesDoNotCollideWithIntegerValues(): void
    {
        $this->assertCardinalitiesRemainDistinct([true, 1]);
        $this->assertCardinalitiesRemainDistinct([false, 0]);
    }

    public function testNullCardinalityDoesNotCollideWithBasicValues(): void
    {
        $this->assertCardinalitiesRemainDistinct([null, true]);
        $this->assertCardinalitiesRemainDistinct([null, false]);
        $this->assertCardinalitiesRemainDistinct([null, 0]);
        $this->assertCardinalitiesRemainDistinct([null, '']);
    }

    private function assertCardinalitiesRemainDistinct(array $values): void
    {
        $index = new IndexedField('Value');
        $records = [];

        foreach ($values as $value) {
            $record = new stdClass();
            $record->Value = $value;
            $records[] = $record;
            $index->set($record);
        }

        foreach ($values as $position => $value) {
            $this->assertSame(
                [spl_object_id($records[$position]) => true],
                $index->find($value)
            );
        }
    }
}
