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

class GetAllByField extends AbstractGetter
{
    public static function handle(Collection $collection, $field = null, $value = null)
    {
        $records = [];

        if (isset($collection->Indexes[$field])) {
            $results = $collection->Indexes[$field]->find($value);

            if ($results) {
                foreach ($results as $key => $_found) {
                    if (isset($collection->HashKeyIndex[$key])) {
                        $records[] = $collection->HashKeyIndex[$key];
                    }
                }
            }
        }

        $className = get_class($collection);

        return new $className($records, array_keys($collection->Indexes));
    }
}
