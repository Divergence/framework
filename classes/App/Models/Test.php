<?php
namespace App\Models;	


class Test extends \Divergence\Models\Model
{
	use \Divergence\Models\Versioning;
	use \Divergence\Models\Relations;
	
	// support subclassing
	static public $rootClass = __CLASS__;
	static public $defaultClass = __CLASS__;
	static public $subClasses = array(__CLASS__);


	// ActiveRecord configuration
	static public $tableName = 'tests';
	static public $singularNoun = 'test';
	static public $pluralNoun = 'tests';
	
	// versioning
	static public $historyTable = 'test_history';
	//static public $createRevisionOnDestroy = true;
	//static public $createRevisionOnSave = true;
	
	static public $fields = array(
        
	);
	
	static public $relationships = array(
		/*'Positions' => array(
	    	'type' => 'one-many'
	    	,'class' => 'ZonePosition'
	    	,'local'	=>	'id'
	    	,'foreign' => 'zone_id'
	    	//,'conditions' => 'Status != "Deleted"'
	    	,'order' => array('name' => 'ASC')
	    )
	    ,*/
	);
}