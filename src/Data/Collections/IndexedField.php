<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections;

use Divergence\Models\Expr\CriteriaType;
use RuntimeException;

class IndexedField
{
    public $field;
    public $type;

    protected $cardinality = [];

    protected $index = [];

    protected $values = [];

    protected ?array $orderedValues = null;

    protected ?array $orderedRecordKeys = null;

    public function __construct($field, $type = null)
    {
        $this->field = $field;
        $this->type = $type;
    }

    public function rebuildIndex(&$records)
    {
        if ($records) {
            foreach ($records as $record) {
                $this->set($record);
            }
        }
    }

    /**
     * @param mixed $value
     * @return array|false
     */
    public function find($value, int $operator = CriteriaType::Equal)
    {
        if ($operator === CriteriaType::In) {
            return $this->findIn((array) $value);
        }

        if ($operator === CriteriaType::NotIn) {
            return array_diff_key($this->allKeys(), $this->findIn((array) $value) ?: []) ?: false;
        }

        $value = $this->indexableValue($value);

        switch ($operator) {
            case CriteriaType::Equal:
                return $this->findEqual($value);
            case CriteriaType::NotEqual:
                return array_diff_key($this->allKeys(), $this->findEqual($value) ?: []) ?: false;
            case CriteriaType::GreaterThan:
                return $this->findOrdered($value, false, false);
            case CriteriaType::GreaterThanOrEqual:
                return $this->findOrdered($value, false, true);
            case CriteriaType::LessThan:
                return $this->findOrdered($value, true, false);
            case CriteriaType::LessThanOrEqual:
                return $this->findOrdered($value, true, true);
            case CriteriaType::Nulled:
            case CriteriaType::NotExists:
                return $this->findEqual(null);
            case CriteriaType::NotNulled:
            case CriteriaType::Exists:
                return array_diff_key($this->allKeys(), $this->findEqual(null) ?: []) ?: false;
        }

        if ($operator !== CriteriaType::Like && $operator !== CriteriaType::NotLike) {
            throw new RuntimeException(sprintf('Criteria operator "%s" cannot be evaluated in-memory', $operator));
        }

        $matches = [];

        foreach ($this->cardinality as $cardinality => $indexedValue) {
            if ($this->matchesValue($indexedValue, $value, $operator)) {
                $matches += $this->index[$cardinality];
            }
        }

        return $matches ?: false;
    }

    public function findByIndex(IndexedField $Index, int $operator)
    {
        $matches = [];

        foreach ($this->values as $recordKey => $leftCardinality) {
            if (!array_key_exists($recordKey, $Index->values)) {
                continue;
            }

            $rightCardinality = $Index->values[$recordKey];

            if ($this->matchesValue(
                $this->cardinality[$leftCardinality],
                $Index->cardinality[$rightCardinality],
                $operator
            )) {
                $matches[$recordKey] = true;
            }
        }

        return $matches ?: false;
    }

    public function clearExistingIndexForValue($record)
    {
        $recordKey = RecordKey::get($record);

        if (isset($this->values[$recordKey])) {
            $cardinality = $this->values[$recordKey];
            unset($this->index[$cardinality][$recordKey]);
            unset($this->values[$recordKey]);

            // if a cardinality becomes unused completely remove it from the known cardinalities
            if (!$this->index[$cardinality]) {
                unset($this->index[$cardinality], $this->cardinality[$cardinality]);
            }

            $this->invalidateOrdering();
        }
    }

    public function set($record)
    {
        $fieldValue = is_array($record) ? ($record[$this->field] ?? null) : ($record->{$this->field} ?? null);
        $cardinalityValue = $this->indexableValue($fieldValue);

        if (!$this->cardinality_exists($cardinalityValue)) {
            $this->bootstrapCardinality($cardinalityValue);
        }

        $this->clearExistingIndexForValue($record);

        $cardinality = $this->cardinalityKey($cardinalityValue);
        $recordKey = RecordKey::get($record);

        $this->index[$cardinality][$recordKey] = true;
        $this->values[$recordKey] = $cardinality;
        $this->invalidateOrdering();
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function indexableValue($value)
    {
        switch ($this->type) {
            case 'DateString':
            case 'timestamp':
                $timestamp = strtotime($value);
                return $timestamp === false ? $value : $timestamp;

            default:
                $type = gettype($value);
                if ($type === 'float' || $type === 'double') {
                    $value = (string) $value;
                }
                return $value;
        }
    }

    /**
     * @param mixed $cardinality
     * @return boolean
     */
    public function cardinality_exists($cardinality)
    {
        $key = $this->cardinalityKey($cardinality);

        return array_key_exists($key, $this->cardinality)
            && $this->cardinality[$key] === $cardinality;
    }

    /**
     * @param mixed $cardinality
     * @return void
     */
    public function bootstrapCardinality($cardinality)
    {
        $hash = $this->cardinalityKey($cardinality);

        $this->cardinality[$hash] = $cardinality;
        $this->index[$hash] = [];
    }

    protected function cardinalityKey($value): string
    {
        return serialize($value);
    }

    protected function invalidateOrdering(): void
    {
        $this->orderedValues = null;
        $this->orderedRecordKeys = null;
    }

    private function findEqual($value)
    {
        $cardinality = $this->cardinalityKey($value);

        if (array_key_exists($cardinality, $this->cardinality)
            && $this->cardinality[$cardinality] === $value) {
            return $this->index[$cardinality] ?: false;
        }

        return false;
    }

    private function findIn(array $values)
    {
        $matches = [];

        foreach ($values as $value) {
            $matches += $this->findEqual($this->indexableValue($value)) ?: [];
        }

        return $matches ?: false;
    }

    private function findOrdered($value, bool $lessThan, bool $inclusive)
    {
        $this->buildOrdering();

        if ($lessThan) {
            $end = $this->lowerBoundary($value, $inclusive);
            $keys = array_slice($this->orderedRecordKeys, 0, $end);
        } else {
            $start = $this->upperBoundary($value, $inclusive);
            $keys = array_slice($this->orderedRecordKeys, $start);
        }

        return $keys ? array_fill_keys($keys, true) : false;
    }

    private function buildOrdering(): void
    {
        if ($this->orderedValues !== null) {
            return;
        }

        $values = [];
        $recordKeys = [];

        foreach ($this->cardinality as $cardinality => $value) {
            foreach ($this->index[$cardinality] as $recordKey => $_found) {
                $values[] = $value;
                $recordKeys[] = $recordKey;
            }
        }

        if ($values) {
            array_multisort($values, SORT_ASC, SORT_REGULAR, $recordKeys, SORT_ASC, SORT_REGULAR);
        }

        $this->orderedValues = $values;
        $this->orderedRecordKeys = $recordKeys;
    }

    private function lowerBoundary($value, bool $inclusive): int
    {
        $low = 0;
        $high = count($this->orderedValues);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $matches = $inclusive
                ? $this->orderedValues[$middle] <= $value
                : $this->orderedValues[$middle] < $value;

            if ($matches) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    private function upperBoundary($value, bool $inclusive): int
    {
        $low = 0;
        $high = count($this->orderedValues);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $matches = $inclusive
                ? $this->orderedValues[$middle] < $value
                : $this->orderedValues[$middle] <= $value;

            if ($matches) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    private function allKeys(): array
    {
        return array_fill_keys(array_keys($this->values), true);
    }

    protected function matchesValue($indexedValue, $value, int $operator): bool
    {
        switch ($operator) {
            case CriteriaType::NotEqual:
                return $indexedValue != $value;
            case CriteriaType::GreaterThan:
                return $indexedValue > $value;
            case CriteriaType::GreaterThanOrEqual:
                return $indexedValue >= $value;
            case CriteriaType::LessThan:
                return $indexedValue < $value;
            case CriteriaType::LessThanOrEqual:
                return $indexedValue <= $value;
            case CriteriaType::Like:
                return $this->matchesLike($indexedValue, $value);
            case CriteriaType::NotLike:
                return !$this->matchesLike($indexedValue, $value);
            case CriteriaType::In:
                return in_array($indexedValue, (array) $value);
            case CriteriaType::NotIn:
                return !in_array($indexedValue, (array) $value);
            case CriteriaType::Nulled:
            case CriteriaType::NotExists:
                return $indexedValue === null;
            case CriteriaType::NotNulled:
            case CriteriaType::Exists:
                return $indexedValue !== null;
            case CriteriaType::Equal:
                return $indexedValue == $value;
            default:
                throw new RuntimeException(sprintf('Criteria operator "%s" cannot be evaluated in-memory', $operator));
        }
    }

    private function matchesLike($value, $pattern): bool
    {
        $quoted = preg_quote((string) $pattern, '/');
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], $quoted) . '$/i';

        return (bool) preg_match($regex, (string) $value);
    }
}
