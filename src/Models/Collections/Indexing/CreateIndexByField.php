<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Collections\Indexing;

use Divergence\Data\Collections\Collection;
use Divergence\Data\Collections\Indexing\AbstractHandler;
use Divergence\Models\Collections\IndexedRecordField;

class CreateIndexByField extends AbstractHandler
{
    public static function handle(Collection $collection, $field = null): void
    {
        if (!$collection instanceof \Divergence\Models\Collections\RecordCollection) {
            throw new \InvalidArgumentException('CreateIndexByField requires a RecordCollection.');
        }

        $fieldOptions = $collection->recordClassName::getClassFields()[$field] ?? [];
        $type = $fieldOptions['type'] ?? null;
        $index = new IndexedRecordField($field, $type);
        $index->rebuildIndex($collection->Index);
        $collection->Indexes[$field] = $index;
    }
}
