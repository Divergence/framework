<?php

namespace Divergence\IO\Database\Writer;

abstract class AbstractSqlWriter
{
    protected static $aggregateFieldConfigs;

    protected static function getAggregateFieldOptions($recordClass, $field = null)
    {
        if (!isset(static::$aggregateFieldConfigs[$recordClass])) {
            static::$aggregateFieldConfigs[$recordClass] = $recordClass::getClassFields();
        }

        if ($field) {
            return static::$aggregateFieldConfigs[$recordClass][$field];
        }

        return static::$aggregateFieldConfigs[$recordClass];
    }

    protected static function eachNonRevisionField(string $recordClass, callable $callback): void
    {
        foreach (static::getAggregateFieldOptions($recordClass) as $fieldId => $field) {
            if ($field['columnName'] === 'RevisionID') {
                continue;
            }

            $callback($fieldId, $field);
        }
    }

    protected static function normalizeFieldOptions(string $recordClass, string $fieldName): array
    {
        $field = static::getAggregateFieldOptions($recordClass, $fieldName);
        $rootClass = $recordClass::getRootClassName();

        if ($rootClass && !$rootClass::fieldExists($fieldName)) {
            $field['notnull'] = false;
        }

        if (
            $field['columnName'] == 'Class'
            && $field['type'] == 'enum'
            && !in_array($rootClass, $field['values'])
            && !count($rootClass::getStaticSubClasses())
        ) {
            array_unshift($field['values'], $rootClass);
        }

        return $field;
    }

    protected static function getVariableCharacterType(array $field): string
    {
        return sprintf(
            !$field['length'] || $field['type'] == 'varchar' ? 'varchar(%u)' : 'char(%u)',
            $field['length'] ? $field['length'] : 255
        );
    }

    protected static function quoteEnumValues(array $field, string $quote = '"'): string
    {
        $escapedValues = array_map([static::class, 'escape'], $field['values']);

        return join($quote . ',' . $quote, $escapedValues);
    }

    protected static function getTranslatedIndexes(string $recordClass, bool $historyVariant): array
    {
        $indexes = $historyVariant ? [] : $recordClass::$indexes;

        foreach ($indexes as &$index) {
            foreach ($index['fields'] as &$indexField) {
                $indexField = $recordClass::getColumnName($indexField);
            }
        }

        return $indexes;
    }

    protected static function hasContextFields(string $recordClass): bool
    {
        return $recordClass::fieldExists('ContextClass') && $recordClass::fieldExists('ContextID');
    }

    protected static function getTargetTableName(string $recordClass, bool $historyVariant): string
    {
        return $historyVariant ? $recordClass::getHistoryTable() : $recordClass::$tableName;
    }

    protected static function isVersionedRecord(string $recordClass): bool
    {
        return is_subclass_of($recordClass, 'VersionedRecord');
    }

    protected static function appendContextIndex(array &$statements, string $recordClass): void
    {
        if (static::hasContextFields($recordClass)) {
            $statements[] = static::getContextIndex($recordClass);
        }
    }

    protected static function getStandardIndexes(string $recordClass, bool $historyVariant): array
    {
        return static::getTranslatedIndexes($recordClass, $historyVariant);
    }
}
