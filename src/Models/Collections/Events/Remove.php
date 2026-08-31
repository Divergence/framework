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

class Remove extends AbstractHandler
{
    public static function handle(Collection $collection, $record = null): void
    {
        $recordKey = RecordKey::get($record);

        foreach ($collection->Index as $key => $Model) {
            $modelKey = RecordKey::get($Model);

            if ($modelKey === $recordKey) {
                array_splice($collection->Index, $key, 1);
                unset($collection->HashKeyIndex[$modelKey]);
                $collection->clearIndexes($Model);

                if ($collection->position > $key) {
                    --$collection->position;
                }

                return;
            }
        }
    }
}
