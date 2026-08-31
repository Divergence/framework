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
use Divergence\Data\Collections\IndexedField;

class CreateIndexByField extends AbstractHandler
{
    public static function handle(Collection $collection, $field = null): void
    {
        $index = new IndexedField($field);
        $index->rebuildIndex($collection->Index);
        $collection->Indexes[$field] = $index;
    }
}
