<?php
namespace Divergence;

class App
{
    public static $ApplicationPath;
    public static $Config;
    public static $whoops;
    
    public static function init($Path)
    {
        static::$ApplicationPath = $Path;
        
        Controllers\RequestHandler::$templateDirectory = $Path.'/views/';

        static::$Config = static::config('app');
        
        static::registerErrorHandler();
    }
    
    public static function config($Label)
    {
        return require static::$ApplicationPath . '/config/' . $Label . '.php';
    }
    
    public static function registerErrorHandler()
    {
        // only show errors in dev environment
        if (static::$Config['environment'] == 'dev') {
            static::$whoops = new \Whoops\Run;
            
            $Handler = new \Whoops\Handler\PrettyPageHandler;
            $Handler->setPageTitle("Divergence Error");
            
            static::$whoops->pushHandler($Handler);
            static::$whoops->register();
        } else {
            error_reporting(0);
        }
    }
}
