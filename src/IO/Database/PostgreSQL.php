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

class PostgreSQL extends StorageType
{
    protected const UNQUOTED_KEYWORDS = [
        'ALL', 'AND', 'ANY', 'ARRAY', 'AS', 'ASC', 'BETWEEN', 'BY', 'CASE', 'CAST', 'COUNT',
        'CURRENT_DATE', 'CURRENT_SCHEMA', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER',
        'DATE_SUB', 'DEFAULT', 'DELETE', 'DESC', 'DISTINCT', 'DROP', 'ELSE', 'END', 'EXISTS', 'FALSE',
        'FETCH', 'FOR', 'FROM', 'GROUP', 'HAVING', 'ILIKE', 'IN', 'INNER', 'INSERT', 'INTERVAL',
        'INTO', 'IS', 'JOIN', 'LASTVAL', 'LEFT', 'LIKE', 'LIMIT', 'LOCK', 'NOT', 'NOW', 'NULL', 'OFFSET', 'ON',
        'OR', 'ORDER', 'OUTER', 'READ', 'RETURNING', 'RIGHT', 'ROUND', 'SCHEMANAME', 'SELECT', 'SET', 'SQL_CALC_FOUND_ROWS',
        'TABLE', 'TABLES', 'THEN', 'TRUE', 'UNLOCK', 'UPDATE', 'VALUES', 'WHEN', 'WHERE',
    ];

    /**
     * Lightweight compatibility shim for MySQL-style table locks used by tests.
     *
     * @var array<string, string>
     */
    protected static $lockedTables = [];

    /**
     * Emulates MySQL's FOUND_ROWS() behavior by counting rows from the last SELECT.
     *
     * @return string|int|false
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

    public static function insertID()
    {
        return static::oneValue('SELECT LASTVAL()');
    }

    protected static function createConnection(array $config, string $label): PDO
    {
        $config = array_merge([
            'host' => 'localhost',
            'port' => 5432,
        ], $config);

        if (empty($config['database'])) {
            throw new Exception('PostgreSQL configuration requires a "database" value.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'],
            $config['port'],
            $config['database']
        );

        if (!empty($config['sslmode'])) {
            $dsn .= ';sslmode=' . $config['sslmode'];
        }

        $connection = new PDO($dsn, $config['username'], $config['password']);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    }

    protected static function configureConnection(PDO $connection): void
    {
        if (!empty(static::$TimeZone)) {
            $q = $connection->prepare('SET TIME ZONE ?');
            $q->execute([static::$TimeZone]);
        }
    }

    protected static function preprocessQuery($query, $parameters = [])
    {
        $query = parent::preprocessQuery($query, $parameters);

        if (str_contains($query, '`')) {
            $query = static::normalizeDoubleQuotedStrings($query);
        }

        if (preg_match('/^\s*SHOW\s+TABLES(?:\s+WHERE\s+(.+))?\s*;?\s*$/i', $query, $matches)) {
            $query = 'SELECT tablename AS "Tables_in_test" FROM pg_tables WHERE schemaname = current_schema()';

            if (!empty($matches[1])) {
                $where = str_replace('`Tables_in_test`', 'tablename', $matches[1]);
                $query .= ' AND (' . $where . ')';
            }

            $query .= ' ORDER BY tablename';
        }

        $query = preg_replace('/DATE_SUB\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+([A-Z]+)\s*\)/i', "NOW() - INTERVAL '$1 $2'", $query);
        $query = preg_replace('/\bformat\s*\((.+?),\s*2\s*\)/i', 'ROUND((\1)::numeric, 2)', $query);
        $query = preg_replace('/\bLIMIT\s+(\d+)\s*,\s*(\d+)\b/i', 'LIMIT $2 OFFSET $1', $query);
        $query = preg_replace('/`([^`]+)`/', '"$1"', $query);
        $query = str_replace('"id"', '"ID"', $query);
        $query = static::quoteBareIdentifiers($query);

        return $query;
    }

    protected static function normalizeDoubleQuotedStrings(string $query): string
    {
        $result = '';
        $length = strlen($query);
        $state = 'normal';

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];

            if ($state === 'single') {
                $result .= $char;

                if ($char === "'" && ($i === 0 || $query[$i - 1] !== '\\')) {
                    $state = 'normal';
                }

                continue;
            }

            if ($char === "'") {
                $state = 'single';
                $result .= $char;
                continue;
            }

            if ($char !== '"') {
                $result .= $char;
                continue;
            }

            $literal = '';
            $i++;

            while ($i < $length) {
                $next = $query[$i];

                if ($next === '"' && ($i === 0 || $query[$i - 1] !== '\\')) {
                    break;
                }

                $literal .= $next;
                $i++;
            }

            $result .= "'" . str_replace("'", "''", stripcslashes($literal)) . "'";
        }

        return $result;
    }

    protected static function quoteBareIdentifiers(string $query): string
    {
        $result = '';
        $length = strlen($query);
        $state = 'normal';

        for ($i = 0; $i < $length; $i++) {
            $char = $query[$i];

            if ($state === 'single') {
                $result .= $char;

                if ($char === "'" && ($i === 0 || $query[$i - 1] !== '\\')) {
                    $state = 'normal';
                }

                continue;
            }

            if ($state === 'double') {
                $result .= $char;

                if ($char === '"') {
                    $state = 'normal';
                }

                continue;
            }

            if ($char === "'") {
                $state = 'single';
                $result .= $char;
                continue;
            }

            if ($char === '"') {
                $state = 'double';
                $result .= $char;
                continue;
            }

            if (preg_match('/[A-Za-z_]/', $char)) {
                $start = $i;

                while ($i + 1 < $length && preg_match('/[A-Za-z0-9_]/', $query[$i + 1])) {
                    $i++;
                }

                $token = substr($query, $start, $i - $start + 1);
                $upper = strtoupper($token);

                if (
                    in_array($upper, static::UNQUOTED_KEYWORDS, true)
                    || (strtolower($token) === $token && $token !== 'id')
                ) {
                    $result .= $token;
                } else {
                    $result .= '"' . $token . '"';
                }

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    public static function interceptNonQuery(string $query): ?bool
    {
        $query = trim($query);

        if (preg_match('/^UNLOCK\s+TABLES\b/i', $query)) {
            static::$lockedTables = [];
            return true;
        }

        if (preg_match('/^LOCK\s+TABLES\s+"?([^"\s]+)"?\s+READ\b/i', $query, $matches)) {
            static::$lockedTables[strtolower($matches[1])] = 'READ';
            return true;
        }

        if (preg_match('/^(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+"?([^"\s]+)"?/i', $query, $matches)) {
            $table = strtolower($matches[2]);

            if (isset(static::$lockedTables[$table])) {
                throw new RuntimeException(sprintf('Database error: [HY000]table is locked for read only access: %s', $table));
            }
        }

        return null;
    }
}
