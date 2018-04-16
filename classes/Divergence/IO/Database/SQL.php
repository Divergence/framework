<?php
namespace Divergence\IO\Database;

use Divergence\IO\Database\MySQL as DB;

class SQL
{
    public static function getCreateTable($recordClass, $historyVariant = false)
    {
        $queryFields = [];
        $indexes = $historyVariant ? [] : $recordClass::$indexes;
        $fulltextColumns = [];
        
        // history table revisionID field
        if ($historyVariant) {
            $queryFields[] = '`RevisionID` int(10) unsigned NOT NULL auto_increment';
            $queryFields[] = 'PRIMARY KEY (`RevisionID`)';
        }
        
        // compile fields
        $rootClass = !empty($recordClass::$rootClass) ? $recordClass::$rootClass : $recordClass;
        foreach ($recordClass::getClassFields() as $fieldId => $field) {
            //Debug::dump($field, "Field: $field[columnName]");
            if ($field['columnName'] == 'RevisionID') {
                continue;
            }
            
            // force notnull=false on non-rootclass fields
            if ($rootClass && !$rootClass::_fieldExists($fieldId)) {
                $field['notnull'] = false;
            }
            
            // auto-prepend class type
            if ($field['columnName'] == 'Class' && $field['type'] == 'enum' && !in_array($rootClass, $field['values']) && empty($rootClass::$subClasses)) {
                array_unshift($field['values'], $rootClass);
            }
            
            // escape namespaces in field names
            if ($field['columnName'] == 'Class') {
                foreach ($field['values'] as $index=>$value) {
                    $field['values'][$index] = str_replace('\\', '\\\\', $value);
                }
            }
            
            $fieldDef = '`'.$field['columnName'].'`';
            $fieldDef .= ' '.static::getSQLType($field);
            $fieldDef .= ' '. ($field['notnull'] ? 'NOT NULL' : 'NULL');
            
            if ($field['autoincrement'] && !$historyVariant) {
                $fieldDef .= ' auto_increment';
            } elseif (($field['type'] == 'timestamp') && ($field['default'] == 'CURRENT_TIMESTAMP')) {
                $fieldDef .= ' default CURRENT_TIMESTAMP';
            } elseif (empty($field['notnull']) && ($field['default'] == null)) {
                $fieldDef .= ' default NULL';
            } elseif (isset($field['default'])) {
                if ($field['type'] == 'boolean') {
                    $fieldDef .= ' default ' . ($field['default'] ? 1 : 0);
                } else {
                    $fieldDef .= ' default "'.DB::escape($field['default']).'"';
                }
            }
            $queryFields[] = $fieldDef;
            
            if ($field['primary']) {
                if ($historyVariant) {
                    $queryFields[] = 'KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
                } else {
                    $queryFields[] = 'PRIMARY KEY (`'.$field['columnName'].'`)';
                }
            }
            
            if ($field['unique'] && !$historyVariant) {
                $queryFields[] = 'UNIQUE KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
            }
            
            if ($field['index'] && !$historyVariant) {
                $queryFields[] = 'KEY `'.$field['columnName'].'` (`'.$field['columnName'].'`)';
            }
            
            if ($field['fulltext'] && !$historyVariant) {
                $fulltextColumns[] = $field['columnName'];
            }
        }
        
        // context index
        if (!$historyVariant && $recordClass::_fieldExists('ContextClass') && $recordClass::_fieldExists('ContextID')) {
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
            "--\n-- %s for class %s\n--\n"
            ."CREATE TABLE IF NOT EXISTS `%s` (\n\t%s\n) ENGINE=MyISAM DEFAULT CHARSET=%s;",
        

            $historyVariant ? 'History table' : 'Table',
        

            $recordClass,
        

            $historyVariant ? $recordClass::$historyTable : $recordClass::$tableName,
        

            join("\n\t,", $queryFields),
        

            DB::$charset
        );
        
        return $createSQL;
    }
    
    public static function getSQLType($field)
    {
        switch ($field['type']) {
            case 'boolean':
                return 'boolean';
            case 'tinyint':
                return 'tinyint' . ($field['unsigned'] ? ' unsigned' : '') . ($field['zerofill'] ? ' zerofill' : '');
            case 'uint':
                $field['unsigned'] = true;
                // no break
            case 'int':
            case 'integer':
                return 'int' . ($field['unsigned'] ? ' unsigned' : '') . ($field['zerofill'] ? ' zerofill' : '');;
            case 'decimal':
                return sprintf('decimal(%s)', $field['length']) . ($field['unsigned'] ? ' unsigned' : '') . ($field['zerofill'] ? ' zerofill' : '');;
            case 'float':
                return 'float';
            case 'double':
                return 'double';
                
            case 'password':
            case 'string':
            case 'list':
                return $field['length'] ? sprintf('char(%u)', $field['length']) : 'varchar(255)';
            case 'clob':
            case 'serialized':
                return 'text';
            case 'blob':
                return 'blob';
                
            case 'timestamp':
                return 'timestamp';
            case 'date':
                return 'date';
            case 'year':
                return 'year';
                
            case 'enum':
                return sprintf('enum("%s")', join('","', $field['values']));
                
            case 'set':
                return sprintf('set("%s")', join('","', $field['values']));
                
            default:
                die("getSQLType: unhandled type $field[type]");
        }
    }
}
