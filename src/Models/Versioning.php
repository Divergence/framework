<?php
/*
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models;

use Exception;

use Divergence\Helpers\Util;
use Divergence\IO\Database\MySQL as DB;

/**
 * Versioning.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
trait Versioning
{
    public $wasDirty = false;

    public static $versioningFields = [
        'RevisionID' => [
            'columnName' => 'RevisionID',
            'type' => 'integer',
            'unsigned' => true,
            'notnull' => false,
        ],
    ];
    
    public static $versioningRelationships = [
        'History' => [
            'type' => 'history',
            'order' => ['RevisionID' => 'DESC'],
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
        $options['conditions'][static::getPrimaryKey()] = $ID;
        
        return static::getRevisions($options);
    }

    public static function getRevisions($options = [])
    {
        return static::instantiateRecords(static::getRevisionRecords($options));
    }
    
    public static function getRevisionRecords($options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false,
            'conditions' => [],
            'order' => false,
            'limit' => false,
            'offset' => 0,
        ]);
                
        $query = 'SELECT * FROM `%s` WHERE (%s)';
        $params = [
            static::getHistoryTable(),
            count($options['conditions']) ? join(') AND (', static::_mapConditions($options['conditions'])) : 1,
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

    public function beforeVersionedSave()
    {
        $this->wasDirty = false;
        if ($this->isDirty && static::$createRevisionOnSave) {
            // update creation time
            $this->Created = time();
            $this->wasDirty = true;
        }
    }

    public function afterVersionedSave()
    {
        if ($this->wasDirty && static::$createRevisionOnSave) {
            // save a copy to history table
            $recordValues = $this->_prepareRecordValues();
            $set = static::_mapValuesToSet($recordValues);
            DB::nonQuery(
                'INSERT INTO `%s` SET %s',
                [
                    static::getHistoryTable(),
                    join(',', $set),
                ]
            );
        }
    }
}
