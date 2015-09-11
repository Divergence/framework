<?php
namespace Divergence\Models;

use Divergence\IO\Database\MySQL as DB;


trait Versioning
{

	static public $versioningFields = array(
		'RevisionID' => array(
			'columnName' => 'RevisionID'
			,'type' => 'integer'
			,'unsigned' => true
			,'notnull' => false
		)
	);
	
	static public $versioningRelationships = array(
		'OldVersions' => array(
			'type' => 'history'
			,'order' => array('RevisionID' => 'DESC')
		)
	);
	
	
	static public function getHistoryTable()
	{
		if(!static::$historyTable)
		{
			throw new Exception('Static variable $historyTable must be defined to use model versioning.');
		}
		
		return static::$historyTable;
	}
	
	/*
	 * Implement specialized getters
	 */
	static public function getRevisionsByID($ID, $options = array())
	{
		$options['conditions'][static::$primaryKey] = $ID;
		
		return static::getRevisions($options);
	}

	static public function getRevisions($options = array())
	{
		return static::instantiateRecords(static::getRevisionRecords($options));
	}
	
	static public function getRevisionRecords($options = array())
	{
		$options = static::prepareOptions($options, array(
			'indexField' => false
			,'conditions' => array()
			,'order' => false
			,'limit' => false
			,'offset' => 0
		));
				
		$query = 'SELECT * FROM `%s` WHERE (%s)';
		$params = array(
			static::getHistoryTable()
			, count($options['conditions']) ? join(') AND (', static::_mapConditions($options['conditions'])) : 1
		);
		
		if($options['order'])
		{
			$query .= ' ORDER BY ' . join(',', static::_mapFieldOrder($options['order']));
		}
		
		if($options['limit'])
		{
			$query .= sprintf(' LIMIT %u,%u', $options['offset'], $options['limit']);
		}
		
		
		if($options['indexField'])
		{
			return DB::table(static::_cn($options['indexField']), $query, $params);
		}
		else
		{
			return DB::allRecords($query, $params);
		}
	}
}