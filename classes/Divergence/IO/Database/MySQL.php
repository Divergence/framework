<?php
namespace Divergence\IO\Database;

use \PDO as PDO;
use \App\App as App;

class MySQL
{
	// configurables
	static public $TimeZone;
	
	static public $encoding = 'UTF-8';
	static public $charset = 'utf8';
	
	static public $defaultProductionLabel = 'mysql';
	static public $defaultDevLabel = 'dev-mysql';
	
	// protected static properties
	protected static $Connections = array();
	protected static $_record_cache = array();
	protected static $LastStatement;
	protected static $Config;
	
	private static function config()
	{
		if(!static::$Config)
		{
			static::$Config = App::config('db');
		}
		
		var_dump(static::$Config);
		
		return static::$Config;
	}
	
	private static function getDefaultLabel()
	{
		if(App::$Config['environment'] == 'production')
		{
			return static::$defaultProductionLabel;
		}
		else if(App::$Config['environment'] == 'dev')
		{
			return static::$defaultDevLabel;
		}
	}
	
	static public function getConnection($label=null)
	{
		if(!$label)
		{
			$label = static::getDefaultLabel();
		}
	
		if(!isset(self::$Connections[$label]))
		{
			static::config();
			
			$config = array_merge(array(
				'host' => 'localhost'
				,'port' => 3306
			), static::$Config[$label]);
			
			if($config['socket'])
			{
				// socket connection
				$DSN = 'mysql:unix_socket=' . $config['socket'] . ';dbname=' . $config['database'];
			}
			else
			{
				// tcp connection
				$DSN = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] .';dbname=' . $config['database'];
			}
			
			try {
				// try to initiate connection
				self::$Connections[$label] = new PDO($DSN, $config['username'], $config['password']);	
			}
			catch(PDOException $e)
			{
				throw new Exception('Connection failed: ' . $e->getMessage());
			}
			
			// set timezone
			$q = self::$Connections[$label]->prepare('SET time_zone=?');
			$q->execute(array(self::$TimeZone));
		}
		
		return self::$Connections[$label];
	}
	
	// public static methods
	static public function escape($string)
	{
		if(is_array($string))
		{
			foreach($string AS &$sub)
			{
				$sub = self::getConnection()->quote($sub);
			}
		}
		else
		{
			$string = self::getConnection()->quote($string);
		}
		
		$string = substr($string,1,strlen($string)-2);
		
		return $string;
	}
	
	
	static public function affectedRows()
	{
		return self::$LastStatement->rowCount();
	}
	
	static public function foundRows()
	{
		return self::oneValue('SELECT FOUND_ROWS()');
	}
	
	
	static public function insertID()
	{
		return self::getConnection()->lastInsertId();
	}
	
	static public function prepareQuery($query, $parameters = array())
	{
		return self::preprocessQuery($query, $parameters);
	}
	
	// protected static methods
	static protected function preprocessQuery($query, $parameters = array())
	{
		// MICS::dump(array('query'=>$query,'params'=>$parameters), __FUNCTION__);
		
		if ( is_array($parameters) && count($parameters) )
		{
			return vsprintf($query, $parameters);
		}
		elseif( isset($parameters) )
		{
			return sprintf($query, $parameters);
		}
		else
		{
			return $query;
		}
	}
	
	static public function nonQuery($query, $parameters = array(), $errorHandler = null)
	{
		// MICS::dump(func_get_args(), 'nonquery');
		
		$query = self::preprocessQuery($query, $parameters);
		
		// start query log
		$queryLog = self::startQueryLog($query);
		
		// execute query
		$Statement = self::getConnection()->query($query);
		
		if($Statement)
		{
		
			// check for errors
			$ErrorInfo = $Statement->errorInfo();
			
			// handle query error
			if($ErrorInfo[0] != '00000')
			{
				self::handleError($query, $queryLog, $errorHandler);
			}
			
		}
		else
		{
			// check for errors
			$ErrorInfo = self::getConnection()->errorInfo();
			
			// handle query error
			if($ErrorInfo[0] != '00000')
			{
				self::handleError($query, $queryLog, $errorHandler);
			}
		}
		
		static::$LastStatement = $Statement;
		
		// finish query log
		self::finishQueryLog($queryLog);
	}
	
	static public function query($query, $parameters = array(), $errorHandler = null)
	{
		$query = self::preprocessQuery($query, $parameters);
		
		// start query log
		$queryLog = self::startQueryLog($query);
		
		// execute query
		$Statement = self::getConnection()->query($query);
		
		if($Statement)
		{
		
			// check for errors
			$ErrorInfo = $Statement->errorInfo();
			
			// handle query error
			if($ErrorInfo[0] != '00000')
			{
				self::handleError($query, $queryLog, $errorHandler);
			}
			
		}
		else
		{
			// check for errors
			$ErrorInfo = self::getConnection()->errorInfo();
			
			// handle query error
			if($ErrorInfo[0] != '00000')
			{
				self::handleError($query, $queryLog, $errorHandler);
			}
		}
		
		static::$LastStatement = $Statement;
		
		// finish query log
		self::finishQueryLog($queryLog, $result);
		
		return $Statement;
	}
	
	
	static public function table($tableKey, $query, $parameters = array(), $nullKey = '', $errorHandler = null)
	{		
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch_assoc())
		{
			$records[$record[$tableKey] ? $record[$tableKey] : $nullKey] = $record;
		}
		
		// free result
		$result->free();
		
		return $records;
	}
	

	static public function arrayTable($tableKey, $query, $parameters = array(), $errorHandler = null)
	{		
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch_assoc())
		{
			if(!array_key_exists($record[$tableKey], $records))
			{
				$records[$record[$tableKey]] = array();
			}
			
			$records[$record[$tableKey]][] = $record;
		}
		
		// free result
		$result->free();
		
		return $records;
	}
	
	
	static public function valuesTable($tableKey, $valueKey, $query, $parameters = array(), $errorHandler = null)
	{
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch_assoc())
		{
			$records[$record[$tableKey]] = $record[$valueKey];
		}
		
		// free result
		$result->free();
		
		return $records;
	}

	static public function allRecordsWithInstantiation($query, $classMapping, $parameters = array(), $errorHandler = null)
	{
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch_assoc())
		{
			foreach($classMapping AS $key => $class)
			{
				$record[$key] = new $class($record[$key]);
			}
			
			$records[] = $record;
		}
		
		// free result
		$result->free();
		
		return $records;
	}

	static public function allInstances($className, $query, $parameters = array(), $errorHandler = null)
	{
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch_assoc())
		{
			$records[] = new $className($record);
		}
		
		// free result
		$result->free();
		
		return $records;
	}
	
	static public function allRecords($query, $parameters = array(), $errorHandler = null)
	{
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch(PDO::FETCH_ASSOC))
		{
			$records[] = $record;
		}
		
		return $records;
	}
	
	
	static public function allValues($valueKey, $query, $parameters = array(), $errorHandler = null)
	{
		// MICS::dump(array('query' => $query, 'params' => $parameters), 'allRecords');
		
		// execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		$records = array();
		while($record = $result->fetch_assoc())
		{
			$records[] = $record[$valueKey];
		}
		
		// free result
		$result->free();
		
		return $records;
	}
	
	
	static public function clearCachedRecord($cacheKey)
	{
		unset(self::$_record_cache[$cacheKey]);
	}
	
	static public function oneRecordCached($cacheKey, $query, $parameters = array(), $errorHandler = null)
	{

		// check for cached record
		if (array_key_exists($cacheKey, self::$_record_cache))
		{
			// log cache hit
			Debug::log(array(
				'cache_hit' => true
				,'query' => $query
				,'cache_key' => $cacheKey
				,'method' => __FUNCTION__
			));
			
			// return cache hit
			return self::$_record_cache[$cacheKey];
		}
		
		// preprocess and execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		// handle query error
		if($result === false)
		{
			self::handleError($query);
		}
		
		// get record
		$record = $result->fetch(PDO::FETCH_ASSOC);
		
		// save record to cache
		if ($cacheKey)
		{
			self::$_record_cache[$cacheKey] = $record;
		}
		
		// return record
		return $record;
	}
	
	
	static public function oneRecord($query, $parameters = array(), $errorHandler = null)
	{
		// preprocess and execute query
		$result = self::query($query, $parameters, $errorHandler);
		
		// get record
		$record = $result->fetch(PDO::FETCH_ASSOC);
		
		// return record
		return $record;
	}
	
	
	static public function oneValue($query, $parameters = array(), $errorHandler = null)
	{
		$record = self::oneRecord($query, $parameters, $errorHandler);
		
		if($record)
		{
			// return record
			return array_shift($record);
		}
		else
		{
			return false;
		}
	}
	
	
	static public function dump($query, $parameters = array())
	{
		Debug::dump($query, false);
		
		if(count($parameters))
		{
			Debug::dump($parameters, false);
			Debug::dump(self::preprocessQuery($query, $parameters), 'processed');
		}
		
	}
	
	
	
	static public function makeOrderString($order = array())
	{
		$s = '';
		
		foreach($order AS $field => $dir)
		{
			if($s!='') $s .= ',';
			
			$s .= '`'.$field.'` '.$dir;
		}
		
		return $s;
	}
	
	
	static protected function startQueryLog($query)
	{
		if (!Site::$debug)
		{
			return false;
		}
		
		// create a new query log structure
		return array(
			'query' => $query
			,'time_start' => sprintf('%f',microtime(true))
		);
	}
	
	
	static protected function extendQueryLog(&$queryLog, $key, $value)
	{
		if ($queryLog == false)
		{
			return false;
		}
		
		$queryLog[$key] = $value;
	}
	
	
	static protected function finishQueryLog(&$queryLog, $result = false)
	{
		if ($queryLog == false)
		{
			return false;
		}
		
		// save finish time and number of affected rows
		$queryLog['time_finish'] = sprintf('%f',microtime(true));
		$queryLog['time_duration_ms'] = ($queryLog['time_finish'] - $queryLog['time_start']) * 1000;
		//$queryLog['affected_rows'] = self::getConnection()->rowCount();
		
		// save result information
		if($result)
		{
			$queryLog['result_fields'] = $result->field_count;
			$queryLog['result_rows'] = $result->num_rows;
		}
		
		// build backtrace string
		$queryLog['method'] = '';
		$backtrace = debug_backtrace();
		while($backtick = array_shift($backtrace))
		{
			// skip the log routine itself
			if ($backtick['function'] == __FUNCTION__)
			{
				continue;
			}
			
			if ($backtick['class'] != __CLASS__)
			{
				break;
			}
		
			// append function
			if($queryLog['method'] != '') $queryLog['method'] .= '/';
			$queryLog['method'] .= $backtick['function'];

		}
		
		// append to static log
		Debug::log($queryLog);
	}
	
	
	static public function handleError($query = '', $queryLog = false, $parameters = null, $errorHandler = null)
	{
        
        if(is_string($errorHandler)) {
            $errorHandler = explode('::',$errorHandler);   
        }
        if(is_callable($errorHandler, false, $callable))
        {
            return call_user_func($errorHandler,$query,$queryLog, $parameters);
        }
        
		// save queryLog
		if($queryLog)
		{
			$error = static::getConnection()->errorInfo();
			$queryLog['error'] = $error[2];
			self::finishQueryLog($queryLog);
		}
		
		// get error message
		if($query == 'connect')
		{
			$message = mysqli_connect_error();
		}
		elseif(self::getConnection()->errorCode() == 1062)
		{
			throw new Exception(static::getConnection()->errorInfo());
		}
		else
		{
			$error = static::getConnection()->errorInfo();
			$message = $error[2];
		}
		
		// respond
		$report = sprintf("<h1 style='color:red'>Database Error</h1>\n");
		$report .= sprintf("<h2>URI</h2>\n<p>%s</p>\n", htmlspecialchars($_SERVER['REQUEST_URI']));
		$report .= sprintf("<h2>Query</h2>\n<p>%s</p>\n", htmlspecialchars($query));
		$report .= sprintf("<h2>Reported</h2>\n<p>%s</p>\n", htmlspecialchars($message));
			
		//$report .= ErrorHandler::formatBacktrace(debug_backtrace());
					
		if(!empty($GLOBALS['Session']) && $GLOBALS['Session']->Person)
		{
			$report .= sprintf("<h2>User</h2>\n<pre>%s</pre>\n", var_export($GLOBALS['Session']->Person->data, true));
		}

		$report .= sprintf("<h2>Backtrace</h2>\n<pre>%s</pre>\n", htmlspecialchars(print_r(debug_backtrace(), true)));
		
		
		if(Site::$debug)
		{
			die($report);
		}
		else
		{
			//Email::send(Site::$webmasterEmail, 'Database error on '.$_SERVER['HTTP_HOST'], $report);
			die('Error while communicating with database');
		}
	}
	
	
}
