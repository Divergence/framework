<?php
namespace App\Models;

class Test extends \Divergence\Models\Model
{
    use \Divergence\Models\Versioning;
    use \Divergence\Models\Relations;
    
    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];


    // ActiveRecord configuration
    public static $tableName = 'tests';
    public static $singularNoun = 'test';
    public static $pluralNoun = 'tests';
    
    // versioning
    public static $historyTable = 'test_history';
    //static public $createRevisionOnDestroy = true;
    //static public $createRevisionOnSave = true;
    
    public static $fields = [
        
    ];
    
    public static $relationships = [
        /*'Positions' => array(
            'type' => 'one-many'
            ,'class' => 'ZonePosition'
            ,'local'	=>	'id'
            ,'foreign' => 'zone_id'
            //,'conditions' => 'Status != "Deleted"'
            ,'order' => array('name' => 'ASC')
        )
        ,*/
    ];
}
