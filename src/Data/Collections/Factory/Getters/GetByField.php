<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections\Factory\Getters;

use Divergence\Data\Collections\Collection;

class GetByField extends AbstractGetter
{
    public static function handle(Collection $collection, $field = null, $value = null)
    {
        if (!isset($collection->Indexes[$field])) {
            return null;
        }

        $results = $collection->Indexes[$field]->find($value);

        if ($results) {
            $key = array_key_first($results);

            return $collection->HashKeyIndex[$key] ?? null;
        }

        return null;
    }
}
