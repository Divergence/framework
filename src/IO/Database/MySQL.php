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

use PDO;
use Divergence\IO\Database\Writer\MySQL as StorageWriter;
/**
 * MySQL.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class MySQL extends StorageType
{
    /**
     * Runs SELECT FOUND_ROWS() and returns the result.
     * @see https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows
     *
     * @return string|int|false An integer as a string.
     */
    public static function foundRows()
    {
        $storageClass = static::getConnectionType();

        if ($storageClass !== static::class) {
            return $storageClass::foundRows();
        }

        return static::oneValue('SELECT FOUND_ROWS()');
    }

    /**
     * @param array $config
     * @param string $label
     * @return PDO
     */
    protected static function createConnection(array $config, string $label): PDO
    {
        $config = array_merge([
            'host' => 'localhost',
            'port' => 3306,
        ], $config);

        if (isset($config['socket'])) {
            $DSN = 'mysql:unix_socket=' . $config['socket'] . ';dbname=' . $config['database'];
        } else {
            $DSN = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['database'];
        }

        return new PDO($DSN, $config['username'], $config['password']);
    }
}
