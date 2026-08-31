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

class HasIndex extends AbstractHandler
{
    public static function handle(Collection $collection, $field = null): bool
    {
        return isset($collection->Indexes[$field]);
    }
}
