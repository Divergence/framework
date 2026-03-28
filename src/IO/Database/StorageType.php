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

use Divergence\App as App;
use Exception;
use PDO;
use Throwable;

class StorageType extends Connections
{
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
            $data = substr($data, 1, strlen($data) - 2);
            return $data;
        } elseif (is_array($data)) {
            foreach ($data as $key => $string) {
                if (is_string($string)) {
                    $data[$key] = static::escape($string);
                }
            }
            return $data;
        }
        return $data;
    }

    /**
     * Quote a scalar value as a SQL string literal using the active PDO driver.
     *
     * @param mixed $data
     * @return string
     */
    public static function quote($data): string
    {
        return static::getConnection()->quote((string) $data);
    }

    /**
     * Returns affected rows from the last query.
     *
     * @return int Affected row count.
     */
    public static function affectedRows()
    {
        if (isset(static::$LastAffectedRows)) {
            return static::$LastAffectedRows;
        }

        return static::$LastStatement->rowCount();
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
     * @param string $query A database query.
     * @param array|string $parameters Parameter(s) for vsprintf (array) or sprintf (string)
     * @return string A formatted query.
     */
    public static function prepareQuery($query, $parameters = [])
    {
        try {
            $resolvedStorageClass = static::getConnectionType();

            return $resolvedStorageClass::preprocessQuery($query, $parameters);
        } catch (Throwable $e) {
            static::reportThrowable($e, $query);
        }
    }

    /**
     * Run a query that returns no data (like update or insert)
     *
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return void
     */
    public static function nonQuery($query, $parameters = [], $errorHandler = null)
    {
        try {
            $resolvedStorageClass = static::getConnectionType();
            $query = $resolvedStorageClass::preprocessQuery($query, $parameters);

            if (method_exists($resolvedStorageClass, 'interceptNonQuery')) {
                $handled = $resolvedStorageClass::interceptNonQuery($query);

                if ($handled !== null) {
                    return;
                }
            }

            $queryLog = static::startQueryLog($query);
            static::$LastAffectedRows = static::getConnection()->exec($query);
            static::$LastStatement = null;
        } catch (\Exception $e) {
            $ErrorInfo = $e->errorInfo;
            if ($ErrorInfo[0] != '00000') {
                static::handleException($e, $query, $queryLog, $errorHandler);
            }
        } catch (Throwable $e) {
            static::reportThrowable($e, $query, $queryLog ?? false, $errorHandler);
        }

        static::finishQueryLog($queryLog);
    }

    /**
     * Run a query and return a PDO statement
     *
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @throws Exception
     * @return \PDOStatement
     */
    public static function query($query, $parameters = [], $errorHandler = null)
    {
        try {
            $resolvedStorageClass = static::getConnectionType();
            $query = $resolvedStorageClass::preprocessQuery($query, $parameters);
            $queryLog = static::startQueryLog($query);
            static::$LastAffectedRows = null;
            static::$LastStatement = $Statement = static::getConnection()->query($query);
            static::finishQueryLog($queryLog);

            return $Statement;
        } catch (\Exception $e) {
            $ErrorInfo = $e->errorInfo;
            if ($ErrorInfo[0] != '00000') {
                $handledException = static::handleException($e, $query, $queryLog, $errorHandler);
                if (is_a($handledException, \PDOStatement::class)) {
                    static::$LastStatement = $handledException;
                    static::startQueryLog($query);

                    return $handledException;
                } else {
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            static::reportThrowable($e, $query, $queryLog ?? false, $errorHandler);
        }
    }

    /**
     * Runs a query and returns all results as an associative array with $tableKey as the index.
     *
     * @param string $tableKey A column to use as an index for the returned array.
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param string $nullKey Optional fallback column to use as an index if the $tableKey param isn't found in a returned record.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return array Result from query or an empty array if nothing found.
     */
    public static function table($tableKey, $query, $parameters = [], $nullKey = '', $errorHandler = null)
    {
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
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return array Result from query or an empty array if nothing found.
     */
    public static function allRecords($query, $parameters = [], $errorHandler = null)
    {
        $result = static::query($query, $parameters, $errorHandler);

        $records = [];
        while ($record = $result->fetch(PDO::FETCH_ASSOC)) {
            $records[] = $record;
        }

        return $records;
    }

    /**
     * Gets one column from every record.
     *
     * @param string $valueKey The name of the column you want.
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return array
     */
    public static function allValues($valueKey, $query, $parameters = [], $errorHandler = null)
    {
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
     */
    public static function clearCachedRecord($cacheKey)
    {
        unset(static::$_record_cache[$cacheKey]);
    }

    /**
     * Returns the first database record from a query with caching
     *
     * @param string $cacheKey A key for the cache to use for this query.
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return array Result from query or an empty array if nothing found.
     */
    public static function oneRecordCached($cacheKey, $query, $parameters = [], $errorHandler = null)
    {
        if (array_key_exists($cacheKey, static::$_record_cache)) {
            return static::$_record_cache[$cacheKey];
        }

        $result = static::query($query, $parameters, $errorHandler);
        $record = $result->fetch(PDO::FETCH_ASSOC);

        static::$_record_cache[$cacheKey] = $record;

        return $record;
    }

    /**
     * Returns the first database record from a query.
     *
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return array Result from query or an empty array if nothing found.
     */
    public static function oneRecord($query, $parameters = [], $errorHandler = null)
    {
        $result = static::query($query, $parameters, $errorHandler);
        return $result->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the first value of the first database record from a query.
     *
     * @param string $query A database query
     * @param array|string $parameters Optional parameters for vsprintf (array) or sprintf (string) to use for formatting the query.
     * @param callable $errorHandler A callback that will run in the event of an error instead of static::handleException
     * @return string|false First field from the first record from a query or false if nothing found.
     */
    public static function oneValue($query, $parameters = [], $errorHandler = null)
    {
        $record = static::oneRecord($query, $parameters, $errorHandler);

        if (!empty($record)) {
            return array_shift($record);
        } else {
            return false;
        }
    }

    /**
     * Handles any errors that are thrown by PDO.
     *
     * @throws \RuntimeException Database error!
     *
     * @param Exception $e
     * @param string $query The query which caused the error.
     * @param boolean|array $queryLog An array created by startQueryLog containing logging information about this query.
     * @param callable $errorHandler A handler to use instead of this one.
     * @return void|mixed
     */
    public static function handleException(Exception $e, $query = '', $queryLog = false, $errorHandler = null)
    {
        if (is_callable($errorHandler, false, $callable)) {
            return call_user_func($errorHandler, $e, $query, $queryLog);
        }

        if ($queryLog) {
            $error = static::getConnection()->errorInfo();
            $queryLog['error'] = $error[2];
            static::finishQueryLog($queryLog);
        }

        $error = static::getConnection()->errorInfo();
        $message = $error[2];

        if (App::$App->Config['environment'] == 'dev') {
            /** @var \Whoops\Handler\PrettyPageHandler */
            $Handler = \Divergence\App::$App->whoops->popHandler();

            if ($Handler::class === \Whoops\Handler\PrettyPageHandler::class) {
                $Handler->addDataTable('Query Information', [
                    'Query' => $query,
                    'Error' => $message,
                    'ErrorCode' => static::getConnection()->errorCode(),
                ]);
                \Divergence\App::$App->whoops->pushHandler($Handler);
            }
        }

        throw new \RuntimeException(sprintf("Database error: [%s]", static::getConnection()->errorCode()).$message);
    }

    /**
     * Reports non-PDO query preparation/runtime failures through any configured app error handler.
     *
     * @param Throwable $e
     * @param string $query
     * @param boolean|array $queryLog
     * @param callable $errorHandler
     * @return never
     */
    protected static function reportThrowable(Throwable $e, $query = '', $queryLog = false, $errorHandler = null)
    {
        if (is_callable($errorHandler, false, $callable)) {
            $handled = call_user_func($errorHandler, $e, $query, $queryLog);

            if ($handled !== null) {
                throw $e;
            }
        }

        if (
            isset(App::$App)
            && !empty(App::$App->Config['environment'])
            && App::$App->Config['environment'] == 'dev'
            && isset(App::$App->whoops)
        ) {
            App::$App->whoops->handleException($e);
        }

        throw $e;
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
        $query = (string) $query;

        if (is_array($parameters) && count($parameters)) {
            return vsprintf($query, $parameters);
        }

        if (is_array($parameters) || !isset($parameters)) {
            return $query;
        }

        return sprintf($query, $parameters);
    }

    /**
     * Creates an associative array containing the query and time_start
     *
     * @param string $query The query you want to start logging.
     * @return false|array
     */
    protected static function startQueryLog($query)
    {
        if (App::$App->Config['environment'] != 'dev') {
            return false;
        }

        return [
            'query' => $query,
            'time_start' => sprintf('%f', microtime(true)),
        ];
    }

    /**
     * Uses the log array created by startQueryLog and sets timing/result metadata on it.
     *
     * @param array|false $queryLog
     * @param object|false $result
     * @return void|false
     */
    protected static function finishQueryLog(&$queryLog, $result = false)
    {
        if ($queryLog == false) {
            return false;
        }

        $queryLog['time_finish'] = sprintf('%f', microtime(true));
        $queryLog['time_duration_ms'] = ($queryLog['time_finish'] - $queryLog['time_start']) * 1000;

        if ($result) {
            $queryLog['result_fields'] = $result->field_count;
            $queryLog['result_rows'] = $result->num_rows;
        }
    }
}
