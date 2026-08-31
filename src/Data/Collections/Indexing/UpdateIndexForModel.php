<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections\Indexing;

use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\RecordKey;

class UpdateIndexForModel extends AbstractHandler
{
    public static function handle(Collection $collection, $index = null, &$record = null): void
    {
        if (isset($collection->Indexes[$index])) {
            $recordKey = RecordKey::get($record);
            $collection->HashKeyIndex[$recordKey] = $record;
            $collection->Indexes[$index]->set($record);
        }
    }
}
