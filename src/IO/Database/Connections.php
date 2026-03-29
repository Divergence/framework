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

Use \Divergence\App;
use Exception;
use PDO;

class Connections
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
     * Current resolved storage class for the active connection label.
     *
     * @var class-string<Connections>|null
     */
    protected static $currentConnectionType = null;

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
     * Number of affected rows from the last non-result query.
     *
     * @var int|null
     */
    protected static $LastAffectedRows;

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
    public static function setConnection(?string $label = null)
    {
        if ($label === null && static::$currentConnection === null) {
            static::$currentConnection = static::getDefaultLabel();
            static::$currentConnectionType = self::getConnectionTypeForLabel(static::$currentConnection);
            return;
        }

        $config = static::config();
        if (isset($config[$label])) {
            static::$currentConnection = $label;
            static::$currentConnectionType = self::getConnectionTypeForLabel($label);
        } else {
            throw new Exception('The provided label does not exist in the config.');
        }
    }

    /**
     * Attempts to make, store, and return a PDO connection.
     *
     * @param string|null $label A specific connection.
     * @return PDO A PDO connection
     *
     * @throws Exception
     */
    public static function getConnection($label = null)
    {
        if ($label === null) {
            if (static::$currentConnection === null) {
                static::setConnection();
            }
            $label = static::$currentConnection;
        }

        if (!isset(static::$Connections[$label])) {
            $config = static::config();

            if (!isset($config[$label])) {
                throw new Exception('The provided label does not exist in the config.');
            }

            $driverClass = static::class === self::class
                ? self::getConnectionTypeForLabel($label)
                : static::class;

            static::$Connections[$label] = self::createResolvedConnection($driverClass, $config[$label], $label);
            self::configureResolvedConnection($driverClass, static::$Connections[$label]);
        }

        return static::$Connections[$label];
    }

    /**
     * Gets the concrete storage class for the current connection config.
     *
     * @return string
     */
    public static function getConnectionType(): string
    {
        if (static::$currentConnection === null) {
            static::setConnection();
        }

        if (static::$currentConnectionType === null) {
            static::$currentConnectionType = self::getConnectionTypeForLabel(static::$currentConnection);
        }

        return static::$currentConnectionType;
    }

    /**
     * Resolve a backend-specific query class for the active connection.
     *
     * Falls back to the provided class when no dialect-specific implementation exists.
     *
     * @param class-string $queryClass
     * @return class-string
     */
    public static function getQueryClass(string $queryClass): string
    {
        $driverClass = static::getConnectionType();
        $driverName = substr($driverClass, strrpos($driverClass, '\\') + 1);
        $queryName = substr($queryClass, strrpos($queryClass, '\\') + 1);
        $resolvedClass = __NAMESPACE__ . '\\Query\\' . $driverName . '\\' . $queryName;

        return class_exists($resolvedClass) ? $resolvedClass : $queryClass;
    }

    /**
     * Gets the concrete storage class for a specific connection label.
     *
     * @param string|null $label
     * @return string
     */
    protected static function getConnectionTypeForLabel(?string $label): string
    {
        $config = static::config();
        $connectionConfig = $config[$label] ?? [];

        if (array_key_exists('path', $connectionConfig)) {
            return SQLite::class;
        }

        if (($connectionConfig['driver'] ?? null) === 'pgsql') {
            return PostgreSQL::class;
        }

        return MySQL::class;
    }

    /**
     * Gets the database config and sets it to static::$Config
     *
     * @return array static::$Config
     */
    protected static function config()
    {
        if (empty(static::$Config)) {
            static::$Config = App::$App->config('db');
        }

        return static::$Config;
    }

    /**
     * Gets the label we should use in the current run time based on App::$App->Config['environment']
     *
     * @return string|null
     */
    protected static function getDefaultLabel()
    {
        if (App::$App->Config['environment'] == 'production') {
            return static::$defaultProductionLabel;
        } elseif (App::$App->Config['environment'] == 'dev') {
            return static::$defaultDevLabel;
        }
    }

    /**
     * Create a PDO connection for the backend-specific config.
     *
     * @param array $config
     * @param string $label
     * @return PDO
     */
    protected static function createConnection(array $config, string $label): PDO
    {
        throw new Exception('Connections::createConnection must be implemented by a concrete database driver.');
    }

    /**
     * Create a PDO connection for the resolved backend without relying on caller-side late static binding.
     *
     * @param class-string<Connections> $driverClass
     * @param array $config
     * @param string $label
     * @return PDO
     */
    protected static function createResolvedConnection(string $driverClass, array $config, string $label): PDO
    {
        return $driverClass::createConnection($config, $label);
    }

    /**
     * Apply any backend-specific connection configuration after connect.
     *
     * @param PDO $connection
     * @return void
     */
    protected static function configureConnection(PDO $connection): void
    {
        if (!empty(static::$TimeZone)) {
            $q = $connection->prepare('SET time_zone=?');
            $q->execute([static::$TimeZone]);
        }
    }

    /**
     * Apply backend-specific post-connect configuration for a resolved backend.
     *
     * @param class-string<Connections> $driverClass
     * @param PDO $connection
     * @return void
     */
    protected static function configureResolvedConnection(string $driverClass, PDO $connection): void
    {
        $driverClass::configureConnection($connection);
    }
}
