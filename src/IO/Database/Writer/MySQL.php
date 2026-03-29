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
 * MySQL schema writer.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class MySQL extends AbstractSqlWriter
{
    protected const DIRECT_SQL_TYPES = [
        'boolean' => 'boolean',
        'float' => 'float',
        'double' => 'double',
        'clob' => 'text',
        'serialized' => 'text',
        'json' => 'text',
        'blob' => 'blob',
        'timestamp' => 'timestamp',
        'datetime' => 'datetime',
        'time' => 'time',
        'date' => 'date',
        'year' => 'year',
    ];

    /**
     * This is how MySQL escapes it's string under the hood.
     * Keep it. We don't need a database connection to escape strings.
     *
     * @param string $str String to escape.
     * @return string Escaped string.
     */
    public static function escape($str)
    {
        return str_replace(
            ["\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a"],
            ["\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z"],
            $str
        );
    }

    public static function compileFields($recordClass, $historyVariant = false)
    {
        $queryString = [];

        static::eachNonRevisionField($recordClass, function ($fieldId, $field) use (&$queryString, $recordClass, $historyVariant) {
            $queryString[] = static::getFieldDefinition($recordClass, $fieldId, $historyVariant);

            if (!empty($field['primary'])) {
                if ($historyVariant) {
                    $queryString[] = 'KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
                } else {
                    $queryString[] = 'PRIMARY KEY (`'.$field['columnName'].'`)';
                }
            }

            if (!empty($field['unique']) && !$historyVariant) {
                $queryString[] = 'UNIQUE KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
            }

            if (!empty($field['index']) && !$historyVariant) {
                $queryString[] = 'KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
            }
        });

        return $queryString;
    }

    public static function getFullTextColumns($recordClass)
    {
        $fulltextColumns = [];

        static::eachNonRevisionField($recordClass, function ($fieldId, $field) use (&$fulltextColumns) {
            if (!empty($field['fulltext'])) {
                $fulltextColumns[] = $field['columnName'];
            }
        });

        return $fulltextColumns;
    }

    public static function getContextIndex($recordClass)
    {
        return 'KEY `CONTEXT` (`'.$recordClass::getColumnName('ContextClass').'`,`'.$recordClass::getColumnName('ContextID').'`)';
    }

    /**
     * Generates a MySQL create table query from a Divergence\Models\ActiveRecord class.
     *
     * @param string $recordClass Class name
     * @param boolean $historyVariant
     * @return string
     */
    public static function getCreateTable($recordClass, $historyVariant = false)
    {
        $indexes = static::getStandardIndexes($recordClass, $historyVariant);
        $fulltextColumns = [];
        $queryString = static::getMySqlBaseStatements($recordClass, $historyVariant);

        if (!$historyVariant) {
            static::appendContextIndex($queryString, $recordClass);
            $fulltextColumns = static::getFullTextColumns($recordClass);
        }

        static::appendMySqlIndexes($queryString, $fulltextColumns, $indexes);

        $createSQL = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n\t%s\n) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
            static::getTargetTableName($recordClass, $historyVariant),
            join("\n\t,", $queryString)
        );

        // append history table SQL
        if (!$historyVariant && static::isVersionedRecord($recordClass)) {
            $createSQL .= PHP_EOL.PHP_EOL.PHP_EOL.static::getCreateTable($recordClass, true);
        }
        return $createSQL;
    }

    protected static function getMySqlBaseStatements(string $recordClass, bool $historyVariant): array
    {
        $queryString = [];

        if ($historyVariant) {
            $queryString[] = '`RevisionID` int(10) unsigned NOT NULL auto_increment';
            $queryString[] = 'PRIMARY KEY (`RevisionID`)';
        }

        return array_merge($queryString, static::compileFields($recordClass, $historyVariant));
    }

    protected static function appendMySqlIndexes(array &$queryString, array &$fulltextColumns, array $indexes): void
    {
        foreach ($indexes as $indexName => $index) {
            if (!empty($index['fulltext'])) {
                $fulltextColumns = array_unique(array_merge($fulltextColumns, $index['fields']));
                continue;
            }

            $queryString[] = sprintf(
                '%s KEY `%s` (`%s`)',
                !empty($index['unique']) ? 'UNIQUE' : '',
                $indexName,
                join('`,`', $index['fields'])
            );
        }

        if (!empty($fulltextColumns)) {
            $queryString[] = 'FULLTEXT KEY `FULLTEXT` (`'.join('`,`', $fulltextColumns).'`)';
        }
    }

    public static function getSQLType($field)
    {
        if (isset(static::DIRECT_SQL_TYPES[$field['type']])) {
            return static::DIRECT_SQL_TYPES[$field['type']];
        }

        if (in_array($field['type'], ['tinyint', 'smallint', 'mediumint', 'bigint'], true)) {
            return static::getMySqlSizedIntegerType($field);
        }

        if (in_array($field['type'], ['uint', 'int', 'integer'], true)) {
            return static::getMySqlIntegerType($field);
        }

        if ($field['type'] === 'decimal') {
            return sprintf('decimal(%s,%s)', $field['precision'], $field['scale']);
        }

        if (in_array($field['type'], ['password', 'string', 'varchar', 'list'], true)) {
            return static::getVariableCharacterType($field);
        }

        if ($field['type'] === 'binary') {
            return sprintf('binary(%s)', isset($field['length']) ? $field['length'] : 1);
        }

        if ($field['type'] === 'enum') {
            return sprintf('enum("%s")', static::quoteEnumValues($field));
        }

        if ($field['type'] === 'set') {
            return sprintf('set("%s")', static::quoteEnumValues($field));
        }

        throw new Exception("getSQLType: unhandled type $field[type]");
    }

    public static function getFieldDefinition($recordClass, $fieldName, $historyVariant = false)
    {
        $field = static::normalizeFieldOptions($recordClass, $fieldName);
        $fieldDef = static::buildMySqlFieldPrefix($field);
        $fieldDef = static::appendMySqlFieldEncoding($fieldDef, $field);
        $fieldDef .= ' '.($field['notnull'] ? 'NOT NULL' : 'NULL');

        return static::appendMySqlFieldDefault($fieldDef, $field, $historyVariant);
    }

    protected static function getMySqlSizedIntegerType(array $field): string
    {
        return $field['type']
            . ($field['unsigned'] ? ' unsigned' : '')
            . (!empty($field['zerofill']) ? ' zerofill' : '');
    }

    protected static function getMySqlIntegerType(array $field): string
    {
        if ($field['type'] === 'uint') {
            $field['unsigned'] = true;
        }

        return 'int'
            . ($field['unsigned'] ? ' unsigned' : '')
            . (!empty($field['zerofill']) ? ' zerofill' : '');
    }

    protected static function buildMySqlFieldPrefix(array $field): string
    {
        return '`' . $field['columnName'] . '` ' . static::getSQLType($field);
    }

    protected static function appendMySqlFieldEncoding(string $fieldDef, array $field): string
    {
        if (!empty($field['charset'])) {
            $fieldDef .= " CHARACTER SET $field[charset]";
        }

        if (!empty($field['collate'])) {
            $fieldDef .= " COLLATE $field[collate]";
        }

        return $fieldDef;
    }

    protected static function appendMySqlFieldDefault(string $fieldDef, array $field, bool $historyVariant): string
    {
        if ($field['autoincrement'] && !$historyVariant) {
            return $fieldDef . ' auto_increment';
        }

        if (($field['type'] == 'timestamp') && ($field['default'] == 'CURRENT_TIMESTAMP')) {
            return $fieldDef . ' default CURRENT_TIMESTAMP';
        }

        if (empty($field['notnull']) && ($field['default'] == null)) {
            return $fieldDef . ' default NULL';
        }

        if (!isset($field['default'])) {
            return $fieldDef;
        }

        if ($field['type'] == 'boolean') {
            return $fieldDef . ' default ' . ($field['default'] ? 1 : 0);
        }

        return $fieldDef . ' default "' . static::escape($field['default']) . '"';
    }
}
