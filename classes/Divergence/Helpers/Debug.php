<?php
namespace Divergence\Helpers;

use App\App as App;

class Debug
{
	static public $log = array();

	static public function dump($var, $exit = true)
	{
		if(!App::$Config['debug']) return;
	
		print '<pre>';
		print_r($var);
		print '</pre>';
		
		if($exit)
			exit();
		else
			return $var;
	}
	
	static public function dumpVar($var, $exit = true)
	{
		if(!App::$Config['debug']) return;
	
		print '<pre>';
		var_export($var);
		print '</pre>';
		
		if($exit)
			exit();
		else
			return $var;
	}
	
	
	static public function logMessage($message, $source = null)
	{
		return static::log(array('message' => $message), $source);
	}
	
	static public function log($entry, $source = null)
	{
		if(!App::$Config['debug']) return;
	
		static::$log[] = array_merge($entry, array(
			'source' => isset($source) ? $source : static::_detectSource()
			,'time' => sprintf('%f', microtime(true))
		));
	}
	
	static protected function _detectSource()
	{
		$backtrace = debug_backtrace();
		
		while($trace = array_shift($backtrace))
		{
			if (!empty($trace['class']))
			{
				if($trace['class'] == __CLASS__)
				{
					continue;
				}
				return $trace['class'];
			}
			elseif (!empty($trace['file']))
			{
				return basename($trace['file']);
			}
		}

		return basename($_SERVER['SCRIPT_NAME']);
	}
	
	static public function showLog()
	{

		echo '<table><tr><td>#</td><td>Duration (ms)</td><td>Query</td><td>Method</td></tr>';
	
		foreach(static::$log as $LogItem)
		{
			$i++;
			echo "<tr><td>$i</td><td>{$LogItem['time_duration_ms']}</td><td>{$LogItem['query']}</td><td>{$LogItem['method']}</td></tr>";
		}
		
		echo '</table>';
	}
}