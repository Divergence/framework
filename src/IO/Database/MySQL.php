<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\IO\Database;

use Exception;
use PDO as PDO;
use Divergence\App as App;

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
     * Current connection label
     *
     * @var string|null $currentConnection
     */
    public static $currentConnection = null;

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
     * @var \PDOStatement|false|null $LastStatement
     */
    protected static $LastStatement;

    /**
     * In-memory cache of the data in the global database config
     *
     * @var array $Config
     */
    protected static $Config;


    /**
     * Sets the connection that should be returned by getConnection when $label is null
     *
     * @param string $label
     * @return void
     */
    public static function setConnection(string $label=null)
    {
        if ($label === null && static::$currentConnection === null) {
            static::$currentConnection = static::getDefaultLabel();
            return;
        }

        $config = static::config();
        if (isset($config[$label])) {
            static::$currentConnection = $label;
        } else {
            throw new Exception('The provided label does not exist in the config.');
        }
    }

    /**
     * Attempts to make, store, and return a PDO connection.
     * - By default will use the label provided by static::getDefaultLabel()
     * - The label corresponds to a config in /config/db.php
     * - Also sets timezone on the connection based on static::$Timezone
     * - Sets static::$Connections[$label] with the connection after connecting.
     * - If static::$Connections[$label] already exists it will return that.
     *
     * @param string|null $label A specific connection.
     * @return PDO A PDO connection
     *
     * @throws Exception
     *
     * @uses static::$Connections
     * @uses static::getDefaultLabel()
     * @uses static::$Timezone
     * @uses PDO
     */
    public static function getConnection($label=null)
    {
        if ($label === null) {
            if (static::$currentConnection === null) {
                static::setConnection();
            }
            $label = static::$currentConnection;
        }

        if (!isset(static::$Connections[$label])) {
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
                static::$Connections[$label] = new PDO($DSN, $config['username'], $config['password']);
            } catch (\PDOException $e) {
                throw $e;
                //throw new Exception('PDO failed to connect on config "'.$label.'" '.$DSN);
            }

            // set timezone
            if (!empty(static::$TimeZone)) {
                $q = static::$Connections[$label]->prepare('SET time_zone=?');
                $q->execute([static::$TimeZone]);
            }
        }

        return static::$Connections[$label];
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
        return static::$LastStatement->rowCount();
    }

    /**
     * Runs SELECT FOUND_ROWS() and returns the result.
     * @see https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows
     *
     * @return string|int|false An integer as a string.
     */
    public static function foundRows()
    {
        return static::oneValue('SELECT FOUND_ROWS()');
    }

    /**
     * Returns the insert id from the last insert.
     * @see http://php.net/manual/en/pdo.lastinsertid.php
     * @return string An integer as a string usually.
     */
    public static function insertID()
    {
        return static::getConnection()->lastInsertId();
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
        return static::preprocessQuery($query, $parameters);
    }

    /**
     * Run a query that returns no data (like update or insert)
     *
     * This method will still set static::$LastStatement
     *
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return void
     */
    public static function nonQuery($query, $parameters = [], $errorHandler = null)
    {
        $query = static::preprocessQuery($query, $parameters);

        // start query log
        $queryLog = static::startQueryLog($query);

        // execute query
        try {
            static::$LastStatement = static::getConnection()->query($query);
        } catch (\Exception $e) {
            $ErrorInfo = $e->errorInfo;
            if ($ErrorInfo[0] != '00000') {
                static::handleException($e, $query, $queryLog, $errorHandler);
            }
        }

        // finish query log
        static::finishQueryLog($queryLog);
    }

    /**
     * Run a query and returns a PDO statement
     *
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @throws Exception
     * @return \PDOStatement
     */
    public static function query($query, $parameters = [], $errorHandler = null)
    {
        $query = static::preprocessQuery($query, $parameters);

        // start query log
        $queryLog = static::startQueryLog($query);

        // execute query
        try {
            static::$LastStatement = $Statement = static::getConnection()->query($query);
            // finish query log
            static::finishQueryLog($queryLog);

            return $Statement;
        } catch (\Exception $e) {
            $ErrorInfo = $e->errorInfo;
            if ($ErrorInfo[0] != '00000') {
                // handledException should return a PDOStatement from a successful query so let's pass this up
                $handledException = static::handleException($e, $query, $queryLog, $errorHandler);
                if (is_a($handledException, \PDOStatement::class)) {
                    static::$LastStatement = $handledException;
                    // start query log
                    $queryLog = static::startQueryLog($query);

                    return $handledException;
                } else {
                    throw $e;
                }
            }
        }
    }

    /*
     *  Uses $tableKey instead of primaryKey (usually ID) as the PHP array index
     *      Only do this with unique indexed fields. This is a helper method for that exact situation.
     */
    /**
     * Runs a query and returns all results as an associative array with $tableKey as the index instead of auto assignment in order of appearance by PHP.
     *
     * @param string $tableKey A column to use as an index for the returned array.
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param string $nullKey Optional fallback column to use as an index if the $tableKey param isn't found in a returned record.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return array Result from query or an empty array if nothing found.
     */
    public static function table($tableKey, $query, $parameters = [], $nullKey = '', $errorHandler = null)
    {
        // execute query
        $result = static::query($query, $parameters, $errorHandler);

        $records = [];
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[$record[$tableKey] ? $record[$tableKey] : $nullKey] = $record;
        }

        return $records;
    }

    /**
     * Runs a query and returns all results as an associative array.
     *
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return array Result from query or an empty array if nothing found.
     */
    public static function allRecords($query, $parameters = [], $errorHandler = null)
    {
        // execute query
        $result = static::query($query, $parameters, $errorHandler);

        $records = [];
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[] = $record;
        }

        return $records;
    }


    /**
     * Gets you some column from every record.
     *
     * @param string $valueKey The name of the column you want.
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return array The column provided in $valueKey from each found record combined as an array. Will be an empty array if no records are found.
     */
    public static function allValues($valueKey, $query, $parameters = [], $errorHandler = null)
    {
        // execute query
        $result = static::query($query, $parameters, $errorHandler);

        $records = [];
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[] = $record[$valueKey];
        }

        return $records;
    }

    /**
     * Unsets static::$_record_cache[$cacheKey]
     *
     * @param string $cacheKey
     * @return void
     *
     * @uses static::$_record_cache
     */
    public static function clearCachedRecord($cacheKey)
    {
        unset(static::$_record_cache[$cacheKey]);
    }

    /**
     * Returns the first database record from a query with caching
     *
     * It is recommended that you LIMIT 1 any records you want out of this to avoid having the database doing any work.
     *
     * @param string $cacheKey A key for the cache to use for this query. If the key is found in the existing cache will return that instead of running the query.
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return array Result from query or an empty array if nothing found.
     *
     * @uses static::$_record_cache
     */
    public static function oneRecordCached($cacheKey, $query, $parameters = [], $errorHandler = null)
    {

        // check for cached record
        if (array_key_exists($cacheKey, static::$_record_cache)) {
            // return cache hit
            return static::$_record_cache[$cacheKey];
        }

        // preprocess and execute query
        $result = static::query($query, $parameters, $errorHandler);

        // get record
        $record = $result->fetch(PDO::FETCH_ASSOC);

        // save record to cache
        static::$_record_cache[$cacheKey] = $record;

        // return record
        return $record;
    }


    /**
     * Returns the first database record from a query.
     *
     * It is recommended that you LIMIT 1 any records you want out of this to avoid having the database doing any work.
     *
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return array Result from query or an empty array if nothing found.
     */
    public static function oneRecord($query, $parameters = [], $errorHandler = null)
    {
        // preprocess and execute query
        $result = static::query($query, $parameters, $errorHandler);

        // get record
        $record = $result->fetch(PDO::FETCH_ASSOC);

        // return record
        return $record;
    }

    /**
     * Returns the first value of the first database record from a query.
     *
     * @param string $query A MySQL query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleError
     * @return string|false First field from the first record from a query or false if nothing found.
     */
    public static function oneValue($query, $parameters = [], $errorHandler = null)
    {
        // get the first record
        $record = static::oneRecord($query, $parameters, $errorHandler);

        if ($record) {
            // return first value of the record
            return array_shift($record);
        } else {
            return false;
        }
    }

    /**
     * Handles any errors that are thrown by PDO
     *
     * If App::$App->Config['environment'] is 'dev' this method will attempt to hook into whoops and provide it with information about this query.
     *
     * @throws \RuntimeException Database error!
     *
     * @param Exception $e
     * @param string $query The query which caused the error.
     * @param boolean|array $queryLog An array created by startQueryLog containing logging information about this query.
     * @param callable $errorHandler An array handler to use instead of this one. If you pass this in it will run first and return directly.
     * @return void|mixed If $errorHandler is set to a callable it will try to run it and return anything that it returns. Otherwise void
     */
    public static function handleException(Exception $e, $query = '', $queryLog = false, $errorHandler = null)
    {
        if (is_callable($errorHandler, false, $callable)) {
            return call_user_func($errorHandler, $e, $query, $queryLog);
        }

        // save queryLog
        if ($queryLog) {
            $error = static::getConnection()->errorInfo();
            $queryLog['error'] = $error[2];
            static::finishQueryLog($queryLog);
        }

        // get error message
        $error = static::getConnection()->errorInfo();
        $message = $error[2];

        if (App::$App->Config['environment']=='dev') {
            $Handler = \Divergence\App::$App->whoops->popHandler();

            $Handler->addDataTable("Query Information", [
                'Query'     	=>	$query,
                'Error'		=>	$message,
                'ErrorCode'	=>	static::getConnection()->errorCode(),
            ]);
            \Divergence\App::$App->whoops->pushHandler($Handler);
        }
        throw new \RuntimeException(sprintf("Database error: [%s]", static::getConnection()->errorCode()).$message);
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

    /**
     * Creates an associative array containing the query and time_start
     *
     * @param string $query The query you want to start logging.
     * @return false|array If App::$App->Config['environment']!='dev' this will return false. Otherwise an array containing 'query' and 'time_start' members.
     */
    protected static function startQueryLog($query)
    {
        if (App::$App->Config['environment']!='dev') {
            return false;
        }

        return [
            'query' => $query,
            'time_start' => sprintf('%f', microtime(true)),
        ];
    }

    /**
     * Uses the log array created by startQueryLog and sets 'time_finish' on it as well as 'time_duration_ms'
     *
     * If a PDO result is passed it will also set 'result_fields' and 'result_rows' on the passed in array.
     *
     * Probably gonna remove this entirely. Query logging should be done via services like New Relic.
     *
     * @param array|false $queryLog Passed by reference. The query log array created by startQueryLog
     * @param object|false $result The result from
     * @return void|false
     */
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

    /**
     * Gets the database config and sets it to static::$Config
     *
     * @uses static::$Config
     * @uses App::config
     *
     * @return array static::$Config
     */
    protected static function config()
    {
        if (!static::$Config) {
            static::$Config = App::$App->config('db');
        }

        return static::$Config;
    }

    /**
     * Gets the label we should use in the current run time based on App::$App->Config['environment']
     *
     * @uses App::$App->Config
     * @uses static::$defaultProductionLabel
     * @uses static::$defaultDevLabel
     *
     * @return string The SQL config to use in the config based on the current environment.
     */
    protected static function getDefaultLabel()
    {
        if (App::$App->Config['environment'] == 'production') {
            return static::$defaultProductionLabel;
        } elseif (App::$App->Config['environment'] == 'dev') {
            return static::$defaultDevLabel;
        }
    }
}
