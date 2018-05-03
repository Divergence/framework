<?php
namespace Divergence\IO\Database;

use \PDO as PDO;
use \Divergence\App as App;
use \Divergence\Helpers\Debug as Debug;

class MySQL
{
    // configurables
    public static $TimeZone;
    
    public static $encoding = 'UTF-8';
    public static $charset = 'utf8';
    
    public static $defaultProductionLabel = 'mysql';
    public static $defaultDevLabel = 'dev-mysql';
    
    // protected static properties
    protected static $Connections = [];
    protected static $_record_cache = [];
    protected static $LastStatement;
    protected static $Config;
    
    public static function getConnection($label=null)
    {
        if (!$label) {
            $label = static::getDefaultLabel();
        }
    
        if (!isset(self::$Connections[$label])) {
            static::config();
            
            $config = array_merge([
                'host' => 'localhost'
                ,'port' => 3306,
            ], static::$Config[$label]);
            
            if ($config['socket']) {
                // socket connection
                $DSN = 'mysql:unix_socket=' . $config['socket'] . ';dbname=' . $config['database'];
            } else {
                // tcp connection
                $DSN = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] .';dbname=' . $config['database'];
            }
            
            try {
                // try to initiate connection
                self::$Connections[$label] = new PDO($DSN, $config['username'], $config['password']);
            } catch (\PDOException $e) {
                throw new \Exception('PDO failed to connect on config "'.$label.'" '.$DSN);
            }
            
            // set timezone
            $q = self::$Connections[$label]->prepare('SET time_zone=?');
            $q->execute([self::$TimeZone]);
        }
        
        return self::$Connections[$label];
    }
    
    // public static methods
    public static function escape($data)
    {
        if (is_string($data)) {
            $data = static::getConnection()->quote($data);
            $data = substr($data, 1, strlen($data)-2);
            return $data;
        } elseif (is_array($data)) {
            foreach ($data as $key=>$string) {
                if (is_string($string)) {
                    $data[$key] = static::escape($string);
                }
            }
            return $data;
        }
        return $data;
    }
    
    
    public static function affectedRows()
    {
        return self::$LastStatement->rowCount();
    }
    
    public static function foundRows()
    {
        return self::oneValue('SELECT FOUND_ROWS()');
    }
    
    
    public static function insertID()
    {
        return self::getConnection()->lastInsertId();
    }
    
    public static function prepareQuery($query, $parameters = [])
    {
        return self::preprocessQuery($query, $parameters);
    }
    
    public static function nonQuery($query, $parameters = [], $errorHandler = null)
    {
        $query = self::preprocessQuery($query, $parameters);
        
        // start query log
        $queryLog = self::startQueryLog($query);
        
        // execute query
        $Statement = self::getConnection()->query($query);
        
        if ($Statement) {
        
            // check for errors
            $ErrorInfo = $Statement->errorInfo();
            
            // handle query error
            if ($ErrorInfo[0] != '00000') {
                self::handleError($query, $queryLog, $errorHandler);
            }
        } else {
            // check for errors
            $ErrorInfo = self::getConnection()->errorInfo();
            
            // handle query error
            if ($ErrorInfo[0] != '00000') {
                self::handleError($query, $queryLog, $errorHandler);
            }
        }
        
        static::$LastStatement = $Statement;
        
        // finish query log
        self::finishQueryLog($queryLog);
    }
    
    public static function query($query, $parameters = [], $errorHandler = null)
    {
        $query = self::preprocessQuery($query, $parameters);
        
        // start query log
        $queryLog = self::startQueryLog($query);
        
        // execute query
        $Statement = self::getConnection()->query($query);
        
        if (!$Statement) {
            // check for errors
            $ErrorInfo = self::getConnection()->errorInfo();
            
            // handle query error
            if ($ErrorInfo[0] != '00000') {
                $ErrorOutput = self::handleError($query, $queryLog, $errorHandler);
                
                if (is_a($ErrorOutput, 'PDOStatement')) {
                    $Statement = $ErrorOutput;
                }
            }
        }
        
        static::$LastStatement = $Statement;
        
        // finish query log
        self::finishQueryLog($queryLog, $result);
        
        return $Statement;
    }
    
    
    public static function table($tableKey, $query, $parameters = [], $nullKey = '', $errorHandler = null)
    {
        // execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        $records = [];
        while ($record = $result->fetch_assoc()) {
            $records[$record[$tableKey] ? $record[$tableKey] : $nullKey] = $record;
        }
        
        // free result
        $result->free();
        
        return $records;
    }
    

    public static function arrayTable($tableKey, $query, $parameters = [], $errorHandler = null)
    {
        // execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        $records = [];
        while ($record = $result->fetch_assoc()) {
            if (!array_key_exists($record[$tableKey], $records)) {
                $records[$record[$tableKey]] = [];
            }
            
            $records[$record[$tableKey]][] = $record;
        }
        
        // free result
        $result->free();
        
        return $records;
    }
    
    
    public static function valuesTable($tableKey, $valueKey, $query, $parameters = [], $errorHandler = null)
    {
        // execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        $records = [];
        while ($record = $result->fetch_assoc()) {
            $records[$record[$tableKey]] = $record[$valueKey];
        }
        
        // free result
        $result->free();
        
        return $records;
    }
    
    public static function allRecords($query, $parameters = [], $errorHandler = null)
    {
        // execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        $records = [];
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[] = $record;
        }
        
        return $records;
    }
    
    
    public static function allValues($valueKey, $query, $parameters = [], $errorHandler = null)
    {
        // execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        $records = [];
        while ($record = $result->fetch_assoc()) {
            $records[] = $record[$valueKey];
        }
        
        // free result
        $result->free();
        
        return $records;
    }
    
    
    public static function clearCachedRecord($cacheKey)
    {
        unset(self::$_record_cache[$cacheKey]);
    }
    
    public static function oneRecordCached($cacheKey, $query, $parameters = [], $errorHandler = null)
    {

        // check for cached record
        if (array_key_exists($cacheKey, self::$_record_cache)) {
            // return cache hit
            return self::$_record_cache[$cacheKey];
        }
        
        // preprocess and execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        // handle query error
        if ($result === false) {
            self::handleError($query);
        }
        
        // get record
        $record = $result->fetch(PDO::FETCH_ASSOC);
        
        // save record to cache
        if ($cacheKey) {
            self::$_record_cache[$cacheKey] = $record;
        }
        
        // return record
        return $record;
    }
    
    
    public static function oneRecord($query, $parameters = [], $errorHandler = null)
    {
        // preprocess and execute query
        $result = self::query($query, $parameters, $errorHandler);
        
        // get record
        $record = $result->fetch(PDO::FETCH_ASSOC);
        
        // return record
        return $record;
    }
    
    
    public static function oneValue($query, $parameters = [], $errorHandler = null)
    {
        $record = self::oneRecord($query, $parameters, $errorHandler);
        
        if ($record) {
            // return record
            return array_shift($record);
        } else {
            return false;
        }
    }
    
    public static function makeOrderString($order = [])
    {
        $s = '';
        
        foreach ($order as $field => $dir) {
            if ($s!='') {
                $s .= ',';
            }
            
            $s .= '`'.$field.'` '.$dir;
        }
        
        return $s;
    }
    
    
    public static function handleError($query = '', $queryLog = false, $errorHandler = null)
    {
        if (is_string($errorHandler)) {
            $errorHandler = explode('::', $errorHandler);
        }
        if (is_callable($errorHandler, false, $callable)) {
            return call_user_func($errorHandler, $query, $queryLog);
        }
        
        // save queryLog
        if ($queryLog) {
            $error = static::getConnection()->errorInfo();
            $queryLog['error'] = $error[2];
            self::finishQueryLog($queryLog);
        }
        
        // get error message
        if ($query == 'connect') {
            $message = mysqli_connect_error();
        } elseif (self::getConnection()->errorCode() == 1062) {
            throw new Exception(static::getConnection()->errorInfo());
        } else {
            $error = static::getConnection()->errorInfo();
            $message = $error[2];
        }
        
        // respond
        if (App::$Config['environment']=='dev') {
            $Handler = \Divergence\App::$whoops->popHandler();
            
            $Handler->addDataTable("Query Information", [
                'Query'     	=>	$query
                ,'Error'		=>	$message
                ,'ErrorCode'	=>	self::getConnection()->errorCode(),
            ]);
            
            \Divergence\App::$whoops->pushHandler($Handler);
            
            throw new \RuntimeException("Database error!");
        } else {
            throw new \RuntimeException("Database error!");
        }
    }
    
    // protected static methods
    protected static function preprocessQuery($query, $parameters = [])
    {
        if (is_array($parameters) && count($parameters)) {
            return vsprintf($query, $parameters);
        } elseif (isset($parameters)) {
            return sprintf($query, $parameters);
        } else {
            return $query;
        }
    }
    
    
    protected static function startQueryLog($query)
    {
        if (App::$Config['environment']!='dev') {
            return false;
        }
        
        // create a new query log structure
        return [
            'query' => $query
            ,'time_start' => sprintf('%f', microtime(true)),
        ];
    }
    
    
    protected static function extendQueryLog(&$queryLog, $key, $value)
    {
        if ($queryLog == false) {
            return false;
        }
        
        $queryLog[$key] = $value;
    }
    
    
    protected static function finishQueryLog(&$queryLog, $result = false)
    {
        if ($queryLog == false) {
            return false;
        }
        
        // save finish time and number of affected rows
        $queryLog['time_finish'] = sprintf('%f', microtime(true));
        $queryLog['time_duration_ms'] = ($queryLog['time_finish'] - $queryLog['time_start']) * 1000;
        //$queryLog['affected_rows'] = self::getConnection()->rowCount();
        
        // save result information
        if ($result) {
            $queryLog['result_fields'] = $result->field_count;
            $queryLog['result_rows'] = $result->num_rows;
        }
        
        // build backtrace string
        $queryLog['method'] = '';
        $backtrace = debug_backtrace();
        while ($backtick = array_shift($backtrace)) {
            // skip the log routine itself
            if ($backtick['function'] == __FUNCTION__) {
                continue;
            }
            
            if ($backtick['class'] != __CLASS__) {
                break;
            }
        
            // append function
            if ($queryLog['method'] != '') {
                $queryLog['method'] .= '/';
            }
            $queryLog['method'] .= $backtick['function'];
        }
    }
    
    private static function config()
    {
        if (!static::$Config) {
            static::$Config = App::config('db');
        }
        
        return static::$Config;
    }
    
    private static function getDefaultLabel()
    {
        if (App::$Config['environment'] == 'production') {
            return static::$defaultProductionLabel;
        } elseif (App::$Config['environment'] == 'dev') {
            return static::$defaultDevLabel;
        }
    }
}
