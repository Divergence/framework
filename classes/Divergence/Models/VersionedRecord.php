<?php
namespace Divergence\Models;

use Divergence\IO\Database\MySQL as DB;

/*	Convert this whole thing to a trait
 *
 */

abstract class VersionedRecord extends ActiveRecord
{


    // configure ActiveRecord
    public static $fields = [
        'RevisionID' => [
            'columnName' => 'RevisionID'
            ,'type' => 'integer'
            ,'unsigned' => true
            ,'notnull' => false,
        ],
    ];
    
    public static $relationships = [
        'OldVersions' => [
            'type' => 'history'
            ,'order' => ['RevisionID' => 'DESC'],
        ],
    ];
    


    // configure VersionedRecord
    public static $historyTable;
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
        $options = static::prepareOptions($options, [
            'indexField' => false
            ,'conditions' => []
            ,'order' => false
            ,'limit' => false
            ,'offset' => 0,
        ]);
                
        $query = 'SELECT * FROM `%s` WHERE (%s)';
        $params = [
            static::$historyTable
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
    
    
    /*
     * Create new revisions on destroy
     */
    public function destroy($createRevision = true)
    {
        if ($createRevision) {
            // save a copy to history table
            $this->Created = time();
            $this->CreatorID = $_SESSION['User'] ? $_SESSION['User']->ID : null;
            if ($this->_fieldExists('end_date')) {
                $this->end_date = date('Y-m-d');
            }
            $recordValues = $this->_prepareRecordValues();
            $set = static::_mapValuesToSet($recordValues);
        
            DB::nonQuery(
                    'INSERT INTO `%s` SET %s',
        
                [
                            static::$historyTable
                            , join(',', $set),
                    ]
            );
        }
        
        $return = parent::destroy();
    }
    
    
    /*
     * Create new revisions on save
     */
    public function save($deep = true, $createRevision = true)
    {
        $wasDirty = false;
        
        if ($this->isDirty && $createRevision) {
            // update creation time / user
            $this->Created = time();
            $this->CreatorID = $_SESSION['User'] ? $_SESSION['User']->ID : null;
            
            $wasDirty = true;
        }
    
        // save record as usual
        $return = parent::save($deep);

        if ($wasDirty && $createRevision) {
            // save a copy to history table
            $recordValues = $this->_prepareRecordValues();
            $set = static::_mapValuesToSet($recordValues);
    
            DB::nonQuery(
                'INSERT INTO `%s` SET %s',
    
                [
                    static::$historyTable
                    , join(',', $set),
                ]
            );
        }
    }


    /*
     * Implement history relationship
     */
    /*public function getValue($name)
    {
        switch($name)
        {
            case 'RevisionID':
            {
                return isset($this->_record['RevisionID']) ? $this->_record['RevisionID'] : null;
            }
            default:
            {
                return parent::getValue($name);
            }
        }
    }*/
    
    protected static function _initRelationship($relationship, $options)
    {
        if ($options['type'] == 'history') {
            if (empty($options['class'])) {
                $options['class'] = get_called_class();
            }
        }

        return parent::_initRelationship($relationship, $options);
    }

    protected function _getRelationshipValue($relationship)
    {
        if (!isset($this->_relatedObjects[$relationship])) {
            $rel = static::$_classRelationships[get_called_class()][$relationship];

            if ($rel['type'] == 'history') {
                $this->_relatedObjects[$relationship] = $rel['class']::getRevisionsByID($this->__get(static::$primaryKey), $rel);
            }
        }
        
        return parent::_getRelationshipValue($relationship);
    }
    
    protected function _setFieldValue($field, $value)
    {
        // ignore setting versioning fields
        if (array_key_exists($field, self::$fields)) {
            return false;
        } else {
            return parent::_setFieldValue($field, $value);
        }
    }
}
