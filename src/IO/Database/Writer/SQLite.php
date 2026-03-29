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
    protected const DIRECT_SQL_TYPES = [
        'boolean' => 'INTEGER',
        'tinyint' => 'INTEGER',
        'smallint' => 'INTEGER',
        'mediumint' => 'INTEGER',
        'bigint' => 'INTEGER',
        'uint' => 'INTEGER',
        'int' => 'INTEGER',
        'integer' => 'INTEGER',
        'year' => 'INTEGER',
        'decimal' => 'REAL',
        'float' => 'REAL',
        'double' => 'REAL',
        'clob' => 'TEXT',
        'serialized' => 'TEXT',
        'json' => 'TEXT',
        'enum' => 'TEXT',
        'set' => 'TEXT',
        'timestamp' => 'TEXT',
        'datetime' => 'TEXT',
        'time' => 'TEXT',
        'date' => 'TEXT',
        'blob' => 'BLOB',
        'binary' => 'BLOB',
    ];

    public static function compileFields($recordClass, $historyVariant = false)
    {
        $queryString = [];

        static::eachNonRevisionField($recordClass, function ($fieldId, $field) use (&$queryString, $recordClass, $historyVariant) {
            $queryString[] = static::getFieldDefinition($recordClass, $fieldId, $historyVariant);

            if (!empty($field['unique']) && !$historyVariant) {
                $queryString[] = 'UNIQUE (`'.$field['columnName'].'`)';
            }
        });

        return $queryString;
    }

    public static function getContextIndex($recordClass)
    {
        return 'CREATE INDEX IF NOT EXISTS `'.$recordClass::$tableName.'_context` ON `'.$recordClass::$tableName.'` (`'.$recordClass::getColumnName('ContextClass').'`,`'.$recordClass::getColumnName('ContextID').'`)';
    }

    public static function getCreateTable($recordClass, $historyVariant = false)
    {
        $indexes = static::getStandardIndexes($recordClass, $historyVariant);
        $queryString = static::getSqliteBaseStatements($recordClass, $historyVariant);
        $postCreateStatements = [];

        if (!$historyVariant) {
            static::appendContextIndex($postCreateStatements, $recordClass);
        }

        static::appendSqliteIndexes($postCreateStatements, $recordClass, $indexes);

        $createSQL = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n\t%s\n);",
            static::getTargetTableName($recordClass, $historyVariant),
            join("\n\t,", $queryString)
        );

        if (!$historyVariant && static::isVersionedRecord($recordClass)) {
            $postCreateStatements[] = static::getCreateTable($recordClass, true);
        }

        if (!empty($postCreateStatements)) {
            $createSQL .= PHP_EOL . PHP_EOL . join(";" . PHP_EOL, $postCreateStatements) . ';';
        }

        return $createSQL;
    }

    protected static function getSqliteBaseStatements(string $recordClass, bool $historyVariant): array
    {
        $queryString = [];

        if ($historyVariant) {
            $queryString[] = '`RevisionID` INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        return array_merge($queryString, static::compileFields($recordClass, $historyVariant));
    }

    protected static function appendSqliteIndexes(array &$postCreateStatements, string $recordClass, array $indexes): void
    {
        foreach ($indexes as $indexName => $index) {
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
    }

    public static function getSQLType($field)
    {
        if (isset(static::DIRECT_SQL_TYPES[$field['type']])) {
            return static::DIRECT_SQL_TYPES[$field['type']];
        }

        if (in_array($field['type'], ['password', 'string', 'varchar', 'list'], true)) {
            return 'TEXT';
        }

        throw new Exception("getSQLType: unhandled type $field[type]");
    }

    public static function getFieldDefinition($recordClass, $fieldName, $historyVariant = false)
    {
        $field = static::normalizeFieldOptions($recordClass, $fieldName);

        if (!empty($field['primary']) && !empty($field['autoincrement']) && !$historyVariant) {
            return '`'.$field['columnName'].'` INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $fieldDef = static::buildSqliteFieldPrefix($field, $historyVariant);
        $fieldDef .= ' '.($field['notnull'] ? 'NOT NULL' : 'NULL');

        return static::appendSqliteFieldDefault($fieldDef, $field);
    }

    protected static function buildSqliteFieldPrefix(array $field, bool $historyVariant): string
    {
        $fieldDef = '`'.$field['columnName'].'` '.static::getSQLType($field);

        if (!empty($field['primary']) && !$historyVariant) {
            $fieldDef .= ' PRIMARY KEY';
        }

        return $fieldDef;
    }

    protected static function appendSqliteFieldDefault(string $fieldDef, array $field): string
    {
        if (($field['type'] == 'timestamp') && ($field['default'] == 'CURRENT_TIMESTAMP')) {
            return $fieldDef . ' DEFAULT CURRENT_TIMESTAMP';
        }

        if (empty($field['notnull']) && ($field['default'] == null)) {
            return $fieldDef . ' DEFAULT NULL';
        }

        if (!isset($field['default'])) {
            return $fieldDef;
        }

        return sprintf(
            "%s DEFAULT '%s'",
            $fieldDef,
            str_replace("'", "''", (string) $field['default'])
        );
    }
}
