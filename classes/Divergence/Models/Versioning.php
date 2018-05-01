<?php
namespace Divergence\Models;

use Exception;

use Divergence\IO\Database\MySQL as DB;

trait Versioning
{
    public static $versioningFields = [
        'RevisionID' => [
            'columnName' => 'RevisionID'
            ,'type' => 'integer'
            ,'unsigned' => true
            ,'notnull' => false,
        ],
    ];
    
    public static $versioningRelationships = [
        'OldVersions' => [
            'type' => 'history'
            ,'order' => ['RevisionID' => 'DESC'],
        ],
    ];
    
    
    public static function getHistoryTable()
    {
        if (!static::$historyTable) {
            throw new Exception('Static variable $historyTable must be defined to use model versioning.');
        }
        
        return static::$historyTable;
    }
    
    /*
     * Implement specialized getters
     */
    public static function getRevisionsByID($ID, $options = [])
    {
        $options['conditions'][static::$primaryKey] = $ID;
        
        return static::getRevisions($options);
    }

    public static function getRevisions($options = [])
    {
        return static::instantiateRecords(static::getRevisionRecords($options));
    }
    
    public static function getRevisionRecords($options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false
            ,'conditions' => []
            ,'order' => false
            ,'limit' => false
            ,'offset' => 0,
        ]);
                
        $query = 'SELECT * FROM `%s` WHERE (%s)';
        $params = [
            static::getHistoryTable()
            , count($options['conditions']) ? join(') AND (', static::_mapConditions($options['conditions'])) : 1,
        ];
        
        if ($options['order']) {
            $query .= ' ORDER BY ' . join(',', static::_mapFieldOrder($options['order']));
        }
        
        if ($options['limit']) {
            $query .= sprintf(' LIMIT %u,%u', $options['offset'], $options['limit']);
        }
        
        
        if ($options['indexField']) {
            return DB::table(static::_cn($options['indexField']), $query, $params);
        } else {
            return DB::allRecords($query, $params);
        }
    }
}
