<?php
namespace Divergence\Controllers;

abstract class RequestHandler
{

	public static $injectableData;	
	public static $responseMode = 'dwoo';
	
	// abstract methods
	abstract public static function handleRequest();
	
	// static properties
	protected static $_path;
	protected static $_parameters;
	protected static $_options = array();
	
	public static $pathStack;
	public static $requestPath;

	public static $templateDirectory;
	

	// protected static methods

	protected static function setPath($path = null)
	{
		if(!static::$pathStack)
		{
			$requestURI = parse_url($_SERVER['REQUEST_URI']);
			static::$pathStack = static::$requestPath = explode('/', ltrim($requestURI['path'], '/'));	
		}
	
		static::$_path = isset($path) ? $path : static::$pathStack;
	}
	
	protected static function setOptions($options)
	{
		static::$_options = isset(self::$_options) ? array_merge(static::$_options, $options) : $options;
	}
	
	
	protected static function peekPath()
	{
		if(!isset(static::$_path)) static::setPath();
		return count(static::$_path) ? static::$_path[0] : false;
	}

	protected static function shiftPath()
	{
		if(!isset(static::$_path)) static::setPath();
		return array_shift(static::$_path);
	}

	protected static function getPath()
	{
		if(!isset(static::$_path)) static::setPath();
		return static::$_path;
	}
	
	protected static function unshiftPath($string)
	{
		if(!isset(static::$_path)) static::setPath();
		return array_unshift(static::$_path, $string);
	}
	
	static public function respond($TemplatePath, $responseData = array(), $responseMode = false)
	{
		if(!headers_sent())
		{
			header('X-TemplatePath: '.$TemplatePath);
			header('Content-Type: text/html; charset=utf-8');
		}
	
		switch($responseMode ? $responseMode : static::$responseMode)
		{
			case 'json':
				JSON::translateAndRespond($responseData);

			case 'jsonp':
				JSONP::translateAndRespond($responseData);

			case 'text':
				header('Content-Type: text/plain');
			
			case 'html':
				if(!file_exists($TemplatePath))
				{
					throw new \Exception($TemplatePath . ' not found.');
				}
				
				if(!is_readable($TemplatePath))
				{
					throw new \Exception($TemplatePath . ' is not readable.');
				}
				
				include($TemplatePath);
				
			case 'dwoo':
				$dwoo = new \Divergence\Templates\Engines\Dwoo();
				
				$data = array(
					'responseID' => $responseID
					,'data' => 	$responseData
				);
				
				if(is_array(static::$injectableData))
				{
					$data = array_merge($data,static::$injectableData);
				}
				
				if(function_exists('fastcgi_finish_request'))
				{
					while(@ob_end_flush());
				}
				
				
				$dwoo->setTemplateDir(static::$templateDirectory);
				echo $dwoo->get($TemplatePath,$data);
				
				if(function_exists('fastcgi_finish_request'))
				{
					fastcgi_finish_request();
				}
				exit;
				
			case 'return':
				return array(
					'TemplatePath'	=> $$TemplatePath
					,'data'			=> $data
				);

			default:
				die('Invalid response mode');
		}
	}
}