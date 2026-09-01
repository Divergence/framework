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

    protected IndexedFieldFinder $finder;

    private const FINDER_METHODS = [
        'find' => 'find',
        'findByIndex' => 'findByIndex',
    ];

    public function __construct($field, $type = null)
    {
        $this->field = $field;
        $this->type = $type;
        $this->finder = new IndexedFieldFinder($this);
    }

    public function __call(string $method, array $arguments)
    {
        return $this->finder->{self::FINDER_METHODS[$method]}(...$arguments);
    }

    public function getCardinality(): array
    {
        return $this->cardinality;
    }

    public function getIndex(): array
    {
        return $this->index;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getOrderedValues(): ?array
    {
        return $this->orderedValues;
    }

    public function setOrderedValues(array $orderedValues): void
    {
        $this->orderedValues = $orderedValues;
    }

    public function getOrderedRecordKeys(): ?array
    {
        return $this->orderedRecordKeys;
    }

    public function setOrderedRecordKeys(array $orderedRecordKeys): void
    {
        $this->orderedRecordKeys = $orderedRecordKeys;
    }

    public function getCardinalityKey($value)
    {
        return $this->cardinalityKey($value);
    }

    public function doesValueMatch($indexedValue, $value, int $operator): bool
    {
        return $this->matchesValue($indexedValue, $value, $operator);
    }

    public function rebuildIndex(&$records)
    {
        if ($records) {
            foreach ($records as $record) {
                $this->set($record);
            }
        }
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

    protected function cardinalityKey($value)
    {
        return serialize($value);
    }

    protected function invalidateOrdering(): void
    {
        $this->orderedValues = null;
        $this->orderedRecordKeys = null;
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
