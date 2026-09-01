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

class IndexedFieldFinder
{
    private const FINDERS = [
        CriteriaType::Equal => 'findEquality',
        CriteriaType::NotEqual => 'findEquality',
        CriteriaType::GreaterThan => 'findOrderedComparison',
        CriteriaType::GreaterThanOrEqual => 'findOrderedComparison',
        CriteriaType::LessThan => 'findOrderedComparison',
        CriteriaType::LessThanOrEqual => 'findOrderedComparison',
        CriteriaType::Like => 'findPattern',
        CriteriaType::NotLike => 'findPattern',
        CriteriaType::In => 'findMembership',
        CriteriaType::NotIn => 'findMembership',
        CriteriaType::Nulled => 'findNullComparison',
        CriteriaType::NotNulled => 'findNullComparison',
        CriteriaType::Exists => 'findNullComparison',
        CriteriaType::NotExists => 'findNullComparison',
    ];

    private const ORDERED_COMPARISONS = [
        CriteriaType::GreaterThan => [false, false],
        CriteriaType::GreaterThanOrEqual => [false, true],
        CriteriaType::LessThan => [true, false],
        CriteriaType::LessThanOrEqual => [true, true],
    ];

    private IndexedField $IndexedField;

    public function __construct(IndexedField $IndexedField)
    {
        $this->IndexedField = $IndexedField;
    }

    /**
     * @param mixed $value
     */
    public function find($value = [], int $operator = CriteriaType::Equal): array
    {
        if (!isset(self::FINDERS[$operator])) {
            throw new RuntimeException(sprintf('Criteria operator "%s" cannot be evaluated in-memory', $operator));
        }

        $finder = self::FINDERS[$operator];

        return $this->{$finder}($value, $operator);
    }

    public function findByIndex(IndexedField $Index, int $operator): array
    {
        $matches = [];

        foreach ($this->IndexedField->getValues() as $recordKey => $leftCardinality) {
            if (!array_key_exists($recordKey, $Index->getValues())) {
                continue;
            }

            $rightCardinality = $Index->getValues()[$recordKey];

            if ($this->IndexedField->doesValueMatch(
                $this->IndexedField->getCardinality()[$leftCardinality],
                $Index->getCardinality()[$rightCardinality],
                $operator
            )) {
                $matches[$recordKey] = true;
            }
        }

        return $matches;
    }

    private function findEquality($value, int $operator): array
    {
        $matches = $this->findEqual($this->IndexedField->indexableValue($value));

        if ($operator === CriteriaType::Equal) {
            return $matches;
        }

        return array_diff_key($this->allKeys(), $matches);
    }

    private function findOrderedComparison($value, int $operator): array
    {
        [$lessThan, $inclusive] = self::ORDERED_COMPARISONS[$operator];

        return $this->findOrdered($this->IndexedField->indexableValue($value), $lessThan, $inclusive);
    }

    private function findPattern($value, int $operator): array
    {
        $value = $this->IndexedField->indexableValue($value);
        $matches = [];

        foreach ($this->IndexedField->getCardinality() as $cardinality => $indexedValue) {
            if ($this->IndexedField->doesValueMatch($indexedValue, $value, $operator)) {
                $matches += $this->IndexedField->getIndex()[$cardinality];
            }
        }

        return $matches;
    }

    private function findMembership($value, int $operator): array
    {
        $matches = $this->findIn((array) $value);

        if ($operator === CriteriaType::In) {
            return $matches;
        }

        return array_diff_key($this->allKeys(), $matches);
    }

    private function findNullComparison($_value, int $operator): array
    {
        $matches = $this->findEqual(null);

        if ($operator === CriteriaType::Nulled || $operator === CriteriaType::NotExists) {
            return $matches;
        }

        return array_diff_key($this->allKeys(), $matches);
    }

    private function findEqual($value): array
    {
        $cardinality = $this->IndexedField->getCardinalityKey($value);

        if (array_key_exists($cardinality, $this->IndexedField->getCardinality())
            && $this->IndexedField->getCardinality()[$cardinality] === $value) {
            return $this->IndexedField->getIndex()[$cardinality];
        }

        return [];
    }

    private function findIn(array $values): array
    {
        $matches = [];

        foreach ($values as $value) {
            $matches += $this->findEqual($this->IndexedField->indexableValue($value));
        }

        return $matches;
    }

    private function findOrdered($value, bool $lessThan, bool $inclusive): array
    {
        $this->buildOrdering();

        if ($lessThan) {
            $end = $this->lowerBoundary($value, $inclusive);
            $keys = array_slice($this->IndexedField->getOrderedRecordKeys(), 0, $end);
        } else {
            $start = $this->upperBoundary($value, $inclusive);
            $keys = array_slice($this->IndexedField->getOrderedRecordKeys(), $start);
        }

        return $keys ? array_fill_keys($keys, true) : [];
    }

    private function buildOrdering(): void
    {
        if ($this->IndexedField->getOrderedValues() !== null) {
            return;
        }

        $values = [];
        $recordKeys = [];

        foreach ($this->IndexedField->getCardinality() as $cardinality => $value) {
            foreach ($this->IndexedField->getIndex()[$cardinality] as $recordKey => $_found) {
                $values[] = $value;
                $recordKeys[] = $recordKey;
            }
        }

        if ($values) {
            array_multisort($values, SORT_ASC, SORT_REGULAR, $recordKeys, SORT_ASC, SORT_REGULAR);
        }

        $this->IndexedField->setOrderedValues($values);
        $this->IndexedField->setOrderedRecordKeys($recordKeys);
    }

    private function lowerBoundary($value, bool $inclusive): int
    {
        $low = 0;
        $high = count($this->IndexedField->getOrderedValues());

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $matches = $inclusive
                ? $this->IndexedField->getOrderedValues()[$middle] <= $value
                : $this->IndexedField->getOrderedValues()[$middle] < $value;

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
        $high = count($this->IndexedField->getOrderedValues());

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $matches = $inclusive
                ? $this->IndexedField->getOrderedValues()[$middle] < $value
                : $this->IndexedField->getOrderedValues()[$middle] <= $value;

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
        return array_fill_keys(array_keys($this->IndexedField->getValues()), true);
    }
}
