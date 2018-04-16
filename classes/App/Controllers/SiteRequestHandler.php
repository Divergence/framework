<?php
namespace App\Controllers;

use \App\App as App;

class SiteRequestHandler extends \Divergence\Controllers\SiteRequestHandler
{
    public static function handleRequest()
    {
        //echo '<pre>';
        
        $Test = new \App\Models\Test();
        
        //var_dump($Test);
        
        //var_dump(App::$Config);
        
        
        $Data = \App\Models\Test::getAllByField('ID', 1);
        
    
        
        
        static::respond('dwoo/design.tpl', [
            'Fields'	=>	\App\Models\Test::getClassFields()
            ,'Test'		=>	$Test
            ,'Data'		=>	$Data,
        ]);
    }
}
