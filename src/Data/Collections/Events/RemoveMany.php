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

class RemoveMany extends AbstractHandler
{
    public static function handle(Collection $collection, $records = []): void
    {
        foreach ($records as $record) {
            $collection->remove($record);
        }
    }
}
