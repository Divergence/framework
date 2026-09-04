<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Events;

use Divergence\IO\Database\Connections;
use Exception;

class HandleException extends AbstractHandler
{
    public static function handle(string $className, Exception $e, $query = null, $queryLog = null, $parameters = null)
    {
        $connection = Connections::getConnection();
        $errorCode = $connection->errorCode();
        $errorInfo = $connection->errorInfo();
        $errorMessage = strtolower($errorInfo[2] ?? $e->getMessage());

        if (static::isMissingTableError($errorCode, $errorMessage) && $className::$autoCreateTables) {
            $transactionStarted = $connection->inTransaction();

            if ($transactionStarted && $connection->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                $connection->rollBack();
            }

            $writerClass = static::getWriterClass();
            $rootClass = $className::getRootClassName();
            $statements = [$writerClass::getCreateTable($rootClass)];

            if ($className::isVersioned()) {
                $statements[] = $writerClass::getCreateTable($rootClass, true);
            }

            $createTable = join(PHP_EOL . PHP_EOL, array_filter($statements));

            foreach (preg_split('/;\s*/', $createTable) as $statementSql) {
                $statementSql = trim($statementSql);

                if ($statementSql === '') {
                    continue;
                }

                $connection->exec($statementSql);
                $errorInfo = $connection->errorInfo();

                if ($errorInfo[0] != '00000') {
                    return static::handle($className, $e, $query, $queryLog, $parameters);
                }
            }

            if ($transactionStarted && !$connection->inTransaction()) {
                $connection->beginTransaction();
            }

            return $connection->query((string) $query);
        }

        return static::getStorage()->handleException($e, $query, $queryLog);
    }

    protected static function isMissingTableError(?string $errorCode, string $errorMessage): bool
    {
        if ($errorCode === '42S02') {
            return true;
        }

        return str_contains($errorMessage, 'no such table')
            || str_contains($errorMessage, 'base table or view not found')
            || str_contains($errorMessage, 'relation')
            && str_contains($errorMessage, 'does not exist');
    }
}
