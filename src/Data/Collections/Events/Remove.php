<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections\Events;

use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\Indexing;
use Divergence\Data\Collections\RecordKey;

class Remove extends AbstractHandler
{
    public static function handle(Collection $collection, $record = null): void
    {
        $recordKey = RecordKey::get($record);

        foreach ($collection->Index as $key => $existing) {
            $existingKey = RecordKey::get($existing);

            if ($existingKey === $recordKey) {
                array_splice($collection->Index, $key, 1);

                if ($collection instanceof Indexing) {
                    unset($collection->HashKeyIndex[$existingKey]);
                    $collection->clearIndexes($existing);
                }

                if ($collection->position > $key) {
                    --$collection->position;
                }

                return;
            }
        }
    }
}
