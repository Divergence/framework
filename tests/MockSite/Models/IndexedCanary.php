<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite\Models;

use Divergence\Models\Mapping\InMemoryIndexing;

/**
 * This test demonstrates a technique where you have the
 * indexed one piggyback on an existing Model definition.
 */
#[InMemoryIndexing(indexes: [
    'ID',
    'Class',
    'Created',
    'CreatorID',
    'ContextID',
    'ContextClass',
    'DNA',
    'Name',
    'Handle',
    'isAlive',
    'DNAHash',
    'StatusCheckedLast',
    'SerializedData',
    'Colors',
    'EyeColors',
    'Height',
    'LongestFlightTime',
    'HighestRecordedAltitude',
    'ObservationCount',
    'DateOfBirth',
    'Weight',
    'RevisionID',
])]
class IndexedCanary extends Canary
{
}
