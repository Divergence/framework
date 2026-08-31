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
use PHPUnit\Framework\TestCase;
use Divergence\Data\Collections\RecordKey;
use Divergence\Tests\MockSite\Models\FixtureItem;

class RecordKeyTest extends TestCase
{
    public function testUsesPrimaryKeyForActiveRecord(): void
    {
        $record = new FixtureItem([
            'ID' => 123,
            'Name' => 'Record',
            'Team' => 1,
            'Score' => 10,
        ], false, false);

        $this->assertSame(123, RecordKey::get($record));
    }

    public function testUsesObjectIdentityForPlainObject(): void
    {
        $record = new stdClass();

        $this->assertSame(spl_object_id($record), RecordKey::get($record));
    }

    public function testUsesObjectIdentityForNonModelWithPrimaryKeyMethod(): void
    {
        $record = new class {
            public function getPrimaryKeyValue(): int
            {
                return 123;
            }
        };

        $this->assertSame(spl_object_id($record), RecordKey::get($record));
    }
}
