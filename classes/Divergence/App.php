<?php
namespace Divergence;

class App
{
	public static $ApplicationPath;
	public static $Config;
	
	public static function init($Path)
	{
		static::$ApplicationPath = $Path;
		
		static::$Config = static::config('app');
		
		static::registerErrorHandler();
	}
	
	public static function config($Label)
	{
		return require static::$ApplicationPath . '/config/' . $Label . '.php';
	}
	
	public static function registerErrorHandler()
	{
		$whoops = new \Whoops\Run;
		$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
		$whoops->register();
	}
}