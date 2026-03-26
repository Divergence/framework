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
use PDO;
use RuntimeException;
use Divergence\IO\Database\Query\AbstractQuery;
use Divergence\IO\Database\Writer\SQLite as StorageWriter;

/**
 * SQLite.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class SQLite extends StorageType
{
    /**
     * Lightweight compatibility shim for MySQL-style table locks used by tests.
     *
     * @var array<string, string>
     */
    protected static $lockedTables = [];

    /**
     * Default config label to use in production
     *
     * @var string $defaultProductionLabel
     */
    public static $defaultProductionLabel = 'sqlite';

    /**
     * Default config label to use in development
     *
     * @var string $defaultDevLabel
     */
    public static $defaultDevLabel = 'dev-sqlite';

    /**
     * Emulates MySQL's FOUND_ROWS() behavior by counting rows from the last SELECT.
     *
     * This is a best-effort compatibility layer for existing pagination code.
     *
     * @return string|int|false An integer as a string, or false if no compatible prior query exists.
     */
    public static function foundRows()
    {
        if (empty(static::$LastStatement) || empty(static::$LastStatement->queryString)) {
            return false;
        }

        $query = trim(static::$LastStatement->queryString);

        if (!preg_match('/^SELECT\b/i', $query)) {
            return false;
        }

        $query = preg_replace('/^SELECT\s+SQL_CALC_FOUND_ROWS\s+/i', 'SELECT ', $query);
        $query = preg_replace('/\s+LIMIT\s+\d+\s*(,\s*\d+)?\s*;?\s*$/i', '', $query);
        $query = preg_replace('/\s+LIMIT\s+\d+\s+OFFSET\s+\d+\s*;?\s*$/i', '', $query);

        return static::oneValue(sprintf('SELECT COUNT(*) FROM (%s) AS divergence_count', $query));
    }

    /**
     * @param array $config
     * @param string $label
     * @return PDO
     */
    protected static function createConnection(array $config, string $label): PDO
    {
        if (empty($config['path'])) {
            throw new Exception('SQLite configuration requires a "path" value.');
        }

        $connection = new PDO('sqlite:' . $config['path']);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!empty($config['foreign_keys'])) {
            $connection->exec('PRAGMA foreign_keys = ON');
        }

        if (!empty($config['busy_timeout'])) {
            $connection->exec(sprintf('PRAGMA busy_timeout = %d', (int) $config['busy_timeout']));
        }

        return $connection;
    }

    /**
     * SQLite has no equivalent to MySQL's time_zone session setting.
     *
     * @param PDO $connection
     * @return void
     */
    protected static function configureConnection(PDO $connection): void
    {
    }

    /**
     * SQLite queries may legitimately contain percent signs in string literals,
     * so avoid sprintf/vsprintf unless parameters were actually provided.
     *
     * @param string $query
     * @param array|string|null $parameters
     * @return string
     */
    protected static function preprocessQuery($query, $parameters = [])
    {
        if ($parameters === null || $parameters === []) {
            return $query;
        }

        return parent::preprocessQuery($query, $parameters);
    }

    public static function interceptNonQuery(string $query): ?bool
    {
        $query = trim($query);

        if (preg_match('/^UNLOCK\s+TABLES\b/i', $query)) {
            static::$lockedTables = [];
            return true;
        }

        if (preg_match('/^LOCK\s+TABLES\s+`?([^`\s]+)`?\s+READ\b/i', $query, $matches)) {
            static::$lockedTables[strtolower($matches[1])] = 'READ';
            return true;
        }

        if (preg_match('/^(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([^`\s]+)`?/i', $query, $matches)) {
            $table = strtolower($matches[2]);

            if (isset(static::$lockedTables[$table])) {
                throw new RuntimeException(sprintf('Database error: [HY000]database table is locked: %s', $table));
            }
        }

        return null;
    }
}
