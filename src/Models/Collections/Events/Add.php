<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Collections\Events;

use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\Events\AbstractHandler;
use Divergence\Data\Collections\RecordKey;

class Add extends AbstractHandler
{
    public static function handle(Collection $collection, $record = null): void
    {
        if ($collection->validate($record)) {
            $primaryKey = $record->getPrimaryKeyValue();
            $modelKey = RecordKey::get($record);

            if (isset($collection->HashKeyIndex[$modelKey])
                && ($primaryKey !== null || $collection->HashKeyIndex[$modelKey] === $record)) {
                return;
            }

            array_push($collection->Index, $record);
            $collection->setIndexes($record);
        }
    }
}
