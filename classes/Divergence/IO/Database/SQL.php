<?php
namespace Divergence\IO\Database;

class SQL
{
    protected static $aggregateFieldConfigs;

    public static function escape($str)
    {
        /*  
         * This is how MySQL escapes it's string under the hood.
         * 
         * Keep it. We don't need a database connection to escape strings.
         */
        return str_replace(
            ["\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a"],
            ["\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z"],
            $str
        );
    }

    public static function getCreateTable($recordClass, $historyVariant = false)
    {
        $queryFields = [];
        //$indexes = $historyVariant ? [] : $recordClass::aggregateStackedConfig('indexes');
        $fulltextColumns = [];

        // history table revisionID field
        if ($historyVariant) {
            $queryFields[] = '`RevisionID` int(10) unsigned NOT NULL auto_increment';
            $queryFields[] = 'PRIMARY KEY (`RevisionID`)';
        }

        // compile fields
        $rootClass = $recordClass::$rootClass;
        foreach (static::getAggregateFieldOptions($recordClass) as $fieldId => $field) {
            if ($field['columnName'] == 'RevisionID') {
                continue;
            }

            $queryFields[] = static::getFieldDefinition($recordClass, $fieldId, $historyVariant);

            if (!empty($field['primary'])) {
                if ($historyVariant) {
                    $queryFields[] = 'KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
                } else {
                    $queryFields[] = 'PRIMARY KEY (`'.$field['columnName'].'`)';
                }
            }

            if (!empty($field['unique']) && !$historyVariant) {
                $queryFields[] = 'UNIQUE KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
            }

            if (!empty($field['index']) && !$historyVariant) {
                $queryFields[] = 'KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
            }

            if (!empty($field['fulltext']) && !$historyVariant) {
                $fulltextColumns[] = $field['columnName'];
            }
        }

        // context index
        if (!$historyVariant && $recordClass::fieldExists('ContextClass') && $recordClass::fieldExists('ContextID')) {
            $queryFields[] = 'KEY `CONTEXT` (`'.$recordClass::getColumnName('ContextClass').'`,`'.$recordClass::getColumnName('ContextID').'`)';
        }

        // compile indexes
        foreach ($indexes as $indexName => $index) {
            if (is_array($index['fields'])) {
                $indexFields = $index['fields'];
            } elseif ($index['fields']) {
                $indexFields = [$index['fields']];
            } else {
                continue;
            }

            // translate field names
            foreach ($index['fields'] as &$indexField) {
                $indexField = $recordClass::getColumnName($indexField);
            }

            if (!empty($index['fulltext'])) {
                $fulltextColumns = array_unique(array_merge($fulltextColumns, $index['fields']));
                continue;
            }

            $queryFields[] = sprintf(
                '%s KEY `%s` (`%s`)',
                !empty($index['unique']) ? 'UNIQUE' : '',
                $indexName,
                join('`,`', $index['fields'])
            );
        }

        if (!empty($fulltextColumns)) {
            $queryFields[] = 'FULLTEXT KEY `FULLTEXT` (`'.join('`,`', $fulltextColumns).'`)';
        }


        $createSQL = sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n\t%s\n) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
            $historyVariant ? $recordClass::getHistoryTableName() : $recordClass::$tableName,
            join("\n\t,", $queryFields)
        );

        // append history table SQL
        if (!$historyVariant && is_subclass_of($recordClass, 'VersionedRecord')) {
            $createSQL .= PHP_EOL.PHP_EOL.PHP_EOL.static::getCreateTable($recordClass, true);
        }

        return $createSQL;
    }

    public static function getSQLType($field)
    {
        switch ($field['type']) {
            case 'boolean':
                return 'boolean';
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                return $field['type'].($field['unsigned'] ? ' unsigned' : '').($field['zerofill'] ? ' zerofill' : '');
            case 'uint':
                $field['unsigned'] = true;
                // no break
            case 'int':
            case 'integer':
                return 'int'.($field['unsigned'] ? ' unsigned' : '').(!empty($field['zerofill']) ? ' zerofill' : '');
            case 'decimal':
                return sprintf('decimal(%s)', $field['length']).(!empty($field['unsigned']) ? ' unsigned' : '').(!empty($field['zerofill']) ? ' zerofill' : '');;
            case 'float':
                return 'float';
            case 'double':
                return 'double';

            case 'password':
            case 'string':
            case 'varchar':
            case 'list':
                return sprintf(!$field['length'] || $field['type'] == 'varchar' ? 'varchar(%u)' : 'char(%u)', $field['length'] ? $field['length'] : 255);
            case 'clob':
            case 'serialized':
            case 'json':
                return 'text';
            case 'blob':
                return 'blob';

            case 'timestamp':
                return 'timestamp';
            case 'datetime':
                return 'datetime';
            case 'time':
                return 'time';
            case 'date':
                return 'date';
            case 'year':
                return 'year';

            case 'enum':
                return sprintf('enum("%s")', join('","', array_map([static::class,'escape'], $field['values'])));

            case 'set':
                return sprintf('set("%s")', join('","', array_map([static::class,'escape'], $field['values'])));

            default:
                die("getSQLType: unhandled type $field[type]");
        }
    }

    public static function getFieldDefinition($recordClass, $fieldName, $historyVariant = false)
    {
        $field = static::getAggregateFieldOptions($recordClass, $fieldName);
        $rootClass = $recordClass::$rootClass;

        // force notnull=false on non-rootclass fields
        if ($rootClass && !$rootClass::fieldExists($fieldName)) {
            $field['notnull'] = false;
        }

        // auto-prepend class type
        if ($field['columnName'] == 'Class' && $field['type'] == 'enum' && !in_array($rootClass, $field['values']) && !count($rootClass::getStaticSubClasses())) {
            array_unshift($field['values'], $rootClass);
        }

        $fieldDef = '`'.$field['columnName'].'`';
        $fieldDef .= ' '.static::getSQLType($field);

        if (!empty($field['charset'])) {
            $fieldDef .= " CHARACTER SET $field[charset]";
        }

        if (!empty($field['collate'])) {
            $fieldDef .= " COLLATE $field[collate]";
        }

        $fieldDef .= ' '.($field['notnull'] ? 'NOT NULL' : 'NULL');

        if ($field['autoincrement'] && !$historyVariant) {
            $fieldDef .= ' auto_increment';
        } elseif (($field['type'] == 'timestamp') && ($field['default'] == 'CURRENT_TIMESTAMP')) {
            $fieldDef .= ' default CURRENT_TIMESTAMP';
        } elseif (empty($field['notnull']) && ($field['default'] == null)) {
            $fieldDef .= ' default NULL';
        } elseif (isset($field['default'])) {
            if ($field['type'] == 'boolean') {
                $fieldDef .= ' default '.($field['default'] ? 1 : 0);
            } else {
                $fieldDef .= ' default "'.static::escape($field['default']).'"';
            }
        }

        return $fieldDef;
    }

    protected static function getAggregateFieldOptions($recordClass, $field = null)
    {
        if (!isset(static::$aggregateFieldConfigs[$recordClass])) {
            static::$aggregateFieldConfigs[$recordClass] = $recordClass::getClassFields();
        }

        if ($field) {
            return static::$aggregateFieldConfigs[$recordClass][$field];
        } else {
            return static::$aggregateFieldConfigs[$recordClass];
        }
    }
}
