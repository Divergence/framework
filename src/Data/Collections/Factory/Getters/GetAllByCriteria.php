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
use Divergence\Models\Expr\Conjunction;
use Divergence\Models\Expr\Criteria;
use Divergence\Models\Expr\CriteriaGroup;
use Divergence\Models\Expr\CriteriaType;

class GetAllByCriteria extends AbstractGetter
{
    /**
     * @param Collection $collection
     * @param Criteria|Criteria[]|CriteriaGroup $CriteriaGroup
     * @return array
     */
    public static function handle(Collection $collection, $CriteriaGroup=[])
    {
        if (is_a($CriteriaGroup, Criteria::class)) {
            $CriteriaGroup = [$CriteriaGroup];
        }

        $output = [];
        if ($found = static::searchByCriteria($collection, $CriteriaGroup)) {
            if (is_array($found)) {
                foreach ($found as $key=>$value) {
                    if (isset($collection->HashKeyIndex[$key])) {
                        $output[] = $collection->HashKeyIndex[$key];
                    }
                }   
            }
        }
		return $output;
    }

    /**
     * @param Collection $collection
     * @param Criteria[]|CriteriaGroup $CriteriaGroup
     * @return array
     */
    private static function searchByCriteria(Collection $collection, $CriteriaGroup)
    {
        if (!is_array($CriteriaGroup) && !is_a($CriteriaGroup, CriteriaGroup::class)) {
			throw new \Exception('Collection->GetAllByCriteria($CriteriaGroup) expects CriteriaGroup[].');
		}

		if (is_array($CriteriaGroup)) {
			$CriteriaGroup = new CriteriaGroup($CriteriaGroup);
		}

        $results = [];
		foreach ($CriteriaGroup->criteria as $crit) {
			if (is_a($crit, Criteria::class)) {
				// just-in-time create the index if needed
                // this is obviously slower than pre-indexing
				if (!isset($collection->Indexes[$crit->key])) {
					$collection->createIndexByField($crit->key);
				}
				// fetch index
				switch ($crit->operator) {
					case CriteriaType::FieldEqual:
						$operator = CriteriaType::Equal;
					break;

					case CriteriaType::FieldNotEqual:
						$operator = CriteriaType::NotEqual;
					break;

					case CriteriaType::FieldGreaterThan:
						$operator = CriteriaType::GreaterThan;
					break;

					case CriteriaType::FieldGreaterThanOrEqual:
						$operator = CriteriaType::GreaterThanOrEqual;
					break;

					case CriteriaType::FieldLessThan:
						$operator = CriteriaType::LessThan;
					break;

					case CriteriaType::FieldLessThanOrEqual:
						$operator = CriteriaType::LessThanOrEqual;
					break;

					default:
						$operator = null;
				}

				if ($operator) {
					if (!isset($collection->Indexes[$crit->value])) {
						$collection->createIndexByField($crit->value);
					}
					$result = $collection->Indexes[$crit->key]->findByIndex($collection->Indexes[$crit->value], $operator);
				} else {
					$result = $collection->Indexes[$crit->key]->find($crit->value, $crit->operator);
				}
				$results[] = $result ?: [];

				// when processing a Group Conjunction::GroupAnd must be found in all indexes to match the operation
				if ($CriteriaGroup->conjunction == Conjunction::GroupAnd && !$result) {
					return [];
				}
			}

			if (is_a($crit, CriteriaGroup::class)) {
				$results[] = static::searchByCriteria($collection, $crit) ?: [];
			}
		}

		// no criteria in the group found anything.
		// we return an empty array immediately.
		if (count($results) === 0) {
			return [];
		}

		// if one thing is found use that one thing
		if (count($results) === 1) {
			$found = array_shift($results);
		}

		if (count($results)>1) {
			switch ($CriteriaGroup->conjunction) {
				case Conjunction::GroupAnd:
				case Conjunction::GroupNotAnd:
					$found = call_user_func_array('array_intersect_key', $results);
				break;

				case Conjunction::GroupOr:
				case Conjunction::GroupNotOr:
					$orKeys = [];
					foreach ($results as $orResults) {
						if (is_array($orResults)) {
							$orKeys = array_merge($orKeys, array_keys($orResults));
						}
                    }
					$results = array_unique($orKeys);
					$found = array_fill_keys($results, 1);
			}
		}

		switch ($CriteriaGroup->conjunction) {
			case Conjunction::GroupNotAnd:
			case Conjunction::GroupNotOr:
				$found = array_diff_key(array_fill_keys(array_keys($collection->HashKeyIndex), 1), $found);
			break;
		}

		return $found ?: [];
    }
}
