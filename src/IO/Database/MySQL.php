<?php
/*
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\IO\Database;

use \PDO as PDO;
use \Divergence\App as App;

/**
 * MySQL.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class MySQL
{
    /**
     * Timezones in TZ format
     *
     * @var string $Timezone
     */
    public static $TimeZone;

    /**
     * Character encoding to use
     *
     * @var string $encoding
     */
    public static $encoding = 'UTF-8';

    /**
     * Character set to use
     *
     * @var string $charset
     */
    public static $charset = 'utf8';

    /**
     * Default config label to use in production
     *
     * @var string $defaultProductionLabel
     */
    public static $defaultProductionLabel = 'mysql';

    /**
     * Default config label to use in development
     *
     * @var string $defaultDevLabel
     */
    public static $defaultDevLabel = 'dev-mysql';

    /**
     * Internal reference list of connections
     *
     * @var array $Connections
     */
    protected static $Connections = [];

    /**
     * In-memory record cache
     *
     * @var array $_record_cache
     */
    protected static $_record_cache = [];

    /**
     * An internal reference to the last PDO statement returned from a query.
     *
     * @var \PDOStatement $LastStatement
     */
    protected static $LastStatement;

    /**
     * In-memory cache of the data in the global database config
     *
     * @var array $Config
     */
    protected static $Config;

    /**
     * Attempts to make, store, and return a PDO connection.
     * - By default will use the label provided by static::getDefaultLabel()
     * - The label corresponds to a config in /config/db.php
     * - Also sets timezone on the connection based on self::$Timezone
     * - Sets self::$Connections[$label] with the connection after connecting.
     * - If self::$Connections[$label] already exists it will return that.
     *
     * @param string|null $label A specific connection.
     * @return PDO A PDO connection
     *
     * @uses self::$Connections
     * @uses static::getDefaultLabel()
     * @uses self::$Timezone
     * @uses PDO
     */
    public static function getConnection($label=null)
    {
        if (!$label) {
            $label = static::getDefaultLabel();
        }

        if (!isset(self::$Connections[$label])) {
            static::config();

            $config = array_merge([
                'host' => 'localhost',
                'port' => 3306,
            ], static::$Config[$label]);

            if (isset($config['socket'])) {
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

    /**
     * Recursive escape for strings or arrays of strings.
     *
     * @param mixed $data If string will do a simple escape. If array will iterate over array members recursively and escape any found strings.
     * @return mixed Same as $data input but with all found strings escaped in place.
     */
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

    /**
     * Returns affected rows from the last query.
     *
     * @return int Affected row count.
     */
    public static function affectedRows()
    {
        return self::$LastStatement->rowCount();
    }

    /**
     * Runs SELECT FOUND_ROWS() and returns the result.
     * @see https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows
     *
     * @return string|int An integer as a string.
     */
    public static function foundRows()
    {
        return self::oneValue('SELECT FOUND_ROWS()');
    }

    /**
     * Returns the insert id from the last insert.
     * @see http://php.net/manual/en/pdo.lastinsertid.php
     * @return string An integer as a string usually.
     */
    public static function insertID()
    {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Formats a query with vsprintf if you pass an array and sprintf if you pass a string.
     *
     *  This is a public pass through for the private method preprocessQuery.
     *
     * @param string $query A database query.
     * @param array|string $parameters Parameter(s) for vsprintf (array) or sprintf (string)
     * @return string A formatted query.
     *
     * @uses static::preprocessQuery
     */
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
        self::finishQueryLog($queryLog);

        return $Statement;
    }

    /*
     *  Uses $tableKey instead of primaryKey (usually ID) as the PHP array index
     *      Only do this with unique indexed fields. This is a helper method for that exact situation.
     */
    public static function table($tableKey, $query, $parameters = [], $nullKey = '', $errorHandler = null)
    {
        // execute query
        $result = self::query($query, $parameters, $errorHandler);

        $records = [];
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[$record[$tableKey] ? $record[$tableKey] : $nullKey] = $record;
        }

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
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[] = $record[$valueKey];
        }

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

    public static function handleError($query = '', $queryLog = false, $errorHandler = null)
    {
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
        $error = static::getConnection()->errorInfo();
        $message = $error[2];

        if (App::$Config['environment']=='dev') {
            $Handler = \Divergence\App::$whoops->popHandler();

            $Handler->addDataTable("Query Information", [
                'Query'     	=>	$query,
                'Error'		=>	$message,
                'ErrorCode'	=>	self::getConnection()->errorCode(),
            ]);

            \Divergence\App::$whoops->pushHandler($Handler);

            throw new \RuntimeException("Database error!");
        } else {
            throw new \RuntimeException("Database error!");
        }
    }

    /**
     * Formats a query with vsprintf if you pass an array and sprintf if you pass a string.
     *
     * @param string $query A database query.
     * @param array|string $parameters Parameter(s) for vsprintf (array) or sprintf (string)
     * @return string A formatted query.
     */
    protected static function preprocessQuery($query, $parameters = [])
    {
        if (is_array($parameters) && count($parameters)) {
            return vsprintf($query, $parameters);
        } else {
            if (isset($parameters)) {
                return sprintf($query, $parameters);
            } else {
                return $query;
            }
        }
    }


    protected static function startQueryLog($query)
    {
        if (App::$Config['environment']!='dev') {
            return false;
        }

        return [
            'query' => $query,
            'time_start' => sprintf('%f', microtime(true)),
        ];
    }

    protected static function finishQueryLog(&$queryLog, $result = false)
    {
        if ($queryLog == false) {
            return false;
        }

        // save finish time and number of affected rows
        $queryLog['time_finish'] = sprintf('%f', microtime(true));
        $queryLog['time_duration_ms'] = ($queryLog['time_finish'] - $queryLog['time_start']) * 1000;

        // save result information
        if ($result) {
            $queryLog['result_fields'] = $result->field_count;
            $queryLog['result_rows'] = $result->num_rows;
        }

        // build backtrace string
        // TODO: figure out a nice toString option that isn't too bulky
        //$queryLog['backtrace'] = debug_backtrace();

        // monolog here
    }

    protected static function config()
    {
        if (!static::$Config) {
            static::$Config = App::config('db');
        }

        return static::$Config;
    }

    protected static function getDefaultLabel()
    {
        if (App::$Config['environment'] == 'production') {
            return static::$defaultProductionLabel;
        } elseif (App::$Config['environment'] == 'dev') {
            return static::$defaultDevLabel;
        }
    }
}
