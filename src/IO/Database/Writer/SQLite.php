<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\IO\Database\Writer;

use Exception;

/**
 * SQLite schema writer.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class SQLite extends MySQL
{
    public static function compileFields($recordClass, $historyVariant = false)
    {
        $queryString = [];
        $fields = static::getAggregateFieldOptions($recordClass);

        foreach ($fields as $fieldId => $field) {
            if ($field['columnName'] == 'RevisionID') {
                continue;
            }

            $queryString[] = static::getFieldDefinition($recordClass, $fieldId, $historyVariant);

            if (!empty($field['unique']) && !$historyVariant) {
                $queryString[] = 'UNIQUE (`'.$field['columnName'].'`)';
            }
        }

        return $queryString;
    }

    public static function getContextIndex($recordClass)
    {
        return 'CREATE INDEX IF NOT EXISTS `'.$recordClass::$tableName.'_context` ON `'.$recordClass::$tableName.'` (`'.$recordClass::getColumnName('ContextClass').'`,`'.$recordClass::getColumnName('ContextID').'`)';
    }

    public static function getCreateTable($recordClass, $historyVariant = false)
    {
        $indexes = $historyVariant ? [] : $recordClass::$indexes;
        $queryString = [];
        $postCreateStatements = [];

        if ($historyVariant) {
            $queryString[] = '`RevisionID` INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $queryString = array_merge($queryString, static::compileFields($recordClass, $historyVariant));

        if (!$historyVariant && $recordClass::fieldExists('ContextClass') && $recordClass::fieldExists('ContextID')) {
            $postCreateStatements[] = static::getContextIndex($recordClass);
        }

        foreach ($indexes as $indexName => $index) {
            foreach ($index['fields'] as &$indexField) {
                $indexField = $recordClass::getColumnName($indexField);
            }

            if (!empty($index['fulltext'])) {
                continue;
            }

            $postCreateStatements[] = sprintf(
                'CREATE %sINDEX IF NOT EXISTS `%s` ON `%s` (`%s`)',
                !empty($index['unique']) ? 'UNIQUE ' : '',
                $indexName,
                $recordClass::$tableName,
                join('`,`', $index['fields'])
            );
        }

        $createSQL = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n\t%s\n);",
            $historyVariant ? $recordClass::getHistoryTable() : $recordClass::$tableName,
            join("\n\t,", $queryString)
        );

        if (!$historyVariant && is_subclass_of($recordClass, 'VersionedRecord')) {
            $postCreateStatements[] = static::getCreateTable($recordClass, true);
        }

        if (!empty($postCreateStatements)) {
            $createSQL .= PHP_EOL . PHP_EOL . join(";" . PHP_EOL, $postCreateStatements) . ';';
        }

        return $createSQL;
    }

    public static function getSQLType($field)
    {
        switch ($field['type']) {
            case 'boolean':
                return 'INTEGER';
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'uint':
            case 'int':
            case 'integer':
            case 'year':
                return 'INTEGER';
            case 'decimal':
            case 'float':
            case 'double':
                return 'REAL';

            case 'password':
            case 'string':
            case 'varchar':
            case 'list':
            case 'clob':
            case 'serialized':
            case 'json':
            case 'enum':
            case 'set':
            case 'timestamp':
            case 'datetime':
            case 'time':
            case 'date':
                return 'TEXT';

            case 'blob':
            case 'binary':
                return 'BLOB';

            default:
                throw new Exception("getSQLType: unhandled type $field[type]");
        }
    }

    public static function getFieldDefinition($recordClass, $fieldName, $historyVariant = false)
    {
        $field = static::getAggregateFieldOptions($recordClass, $fieldName);
        $rootClass = $recordClass::getRootClassName();

        if ($rootClass && !$rootClass::fieldExists($fieldName)) {
            $field['notnull'] = false;
        }

        if ($field['columnName'] == 'Class' && $field['type'] == 'enum' && !in_array($rootClass, $field['values']) && !count($rootClass::getStaticSubClasses())) {
            array_unshift($field['values'], $rootClass);
        }

        if (!empty($field['primary']) && !empty($field['autoincrement']) && !$historyVariant) {
            return '`'.$field['columnName'].'` INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $fieldDef = '`'.$field['columnName'].'` '.static::getSQLType($field);

        if (!empty($field['primary']) && !$historyVariant) {
            $fieldDef .= ' PRIMARY KEY';
        }

        $fieldDef .= ' '.($field['notnull'] ? 'NOT NULL' : 'NULL');

        if (($field['type'] == 'timestamp') && ($field['default'] == 'CURRENT_TIMESTAMP')) {
            $fieldDef .= ' DEFAULT CURRENT_TIMESTAMP';
        } elseif (empty($field['notnull']) && ($field['default'] == null)) {
            $fieldDef .= ' DEFAULT NULL';
        } elseif (isset($field['default'])) {
            $fieldDef .= sprintf(
                " DEFAULT '%s'",
                str_replace("'", "''", (string) $field['default'])
            );
        }

        return $fieldDef;
    }
}
