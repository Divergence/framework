<?php
/**
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
use Divergence\Models\Mapping\Column;
use Divergence\IO\Database\MySQL as DB;
use Divergence\IO\Database\Query\Insert;
use Divergence\IO\Database\Query\Select;

/**
 * Versioning.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 * @inheritDoc
 * @property int $RevisionID ID of revision in the history table.
 * @property static[] $History All revisions for this object. This is hooked in the Relations trait.
 * @property string $historyTable
 * @property callable $createRevisionOnSave
 */
trait Versioning
{
    public $wasDirty = false;

    #[Column(type: "integer", unsigned:true, notnull:false)]
    protected $RevisionID;

    public static $versioningRelationships = [
        'History' => [
            'type' => 'history',
            'order' => ['RevisionID' => 'DESC'],
        ],
    ];

    /**
     * Returns static::$historyTable
     *
     * @throws Exception If static::$historyTable is not set.
     * @return string
     */
    public static function getHistoryTable()
    {
        if (!static::$historyTable) {
            throw new Exception('Static variable $historyTable must be defined to use model versioning.');
        }

        return static::$historyTable;
    }

    /**
     * The history table allows multiple records with the same ID.
     * The primary key becomes RevisionID.
     * This returns all the revisions by ID so you will get the history of that object.
     *
     * @return static[] Revisions by ID
     */
    public static function getRevisionsByID($ID, $options = [])
    {
        $options['conditions'][static::getPrimaryKey()] = $ID;

        return static::getRevisions($options);
    }

    /**
     * Gets all versions of all objects for this object.
     *
     * Use getRevisionsByID instead.
     *
     * @param array $options Query options
     * @return static[] Revisions
     */
    public static function getRevisions($options = [])
    {
        return static::instantiateRecords(static::getRevisionRecords($options));
    }

    /**
     * Gets raw revision data from the database and constructs a query
     *
     * @param array $options Query options
     * @return array
     */
    public static function getRevisionRecords($options = [])
    {
        $options = Util::prepareOptions($options, [
            'indexField' => false,
            'conditions' => [],
            'order' => false,
            'limit' => false,
            'offset' => 0,
        ]);


        $select = (new Select())->setTable(static::getHistoryTable());

        if ($options['conditions']) {
            $select->where(join(') AND (', static::_mapConditions($options['conditions'])));
        }

        if ($options['order']) {
            $select->order(join(',', static::_mapFieldOrder($options['order'])));
        }

        if ($options['limit']) {
            $select->limit(sprintf('%u,%u', $options['offset'], $options['limit']));
        }

        if ($options['indexField']) {
            return DB::table(static::_cn($options['indexField']), $select);
        } else {
            return DB::allRecords($select);
        }
    }

    /**
     * Sets wasDirty, isDirty, Created for the object before save
     *
     * @return void
     */
    public function beforeVersionedSave()
    {
        $this->wasDirty = false;
        if ($this->isDirty && static::$createRevisionOnSave) {
            // update creation time
            $this->Created = time();
            $this->wasDirty = true;
        }
    }

    /**
     * After save to regular database this saves to the history table
     *
     * @return void
     */
    public function afterVersionedSave()
    {
        if ($this->wasDirty && static::$createRevisionOnSave) {
            // save a copy to history table
            $recordValues = $this->_prepareRecordValues();
            $set = static::_mapValuesToSet($recordValues);
            DB::nonQuery((new Insert())->setTable(static::getHistoryTable())->set($set), null, [static::class,'handleError']);
        }
    }
}
