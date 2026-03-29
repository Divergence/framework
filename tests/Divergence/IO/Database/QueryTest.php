<?php

namespace Divergence\Tests\IO\Database;

use Divergence\IO\Database\MySQL;
use Divergence\IO\Database\PostgreSQL;
use Divergence\IO\Database\Connections;
use Divergence\IO\Database\Query\Insert;
use Divergence\Tests\MockSite\App;
use PHPUnit\Framework\TestCase;

class testableMySQLQueryDB extends MySQL
{
    public static function preprocessPublic($query, $parameters = [])
    {
        return static::preprocessQuery($query, $parameters);
    }
}

class testablePostgreSQLQueryDB extends PostgreSQL
{
    public static function preprocessPublic($query, $parameters = [])
    {
        return static::preprocessQuery($query, $parameters);
    }
}

class QueryTest extends TestCase
{
    protected ?string $originalConnection = null;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/';

        if (!isset(App::$App)) {
            new App(__DIR__ . '/../../../../');
        }

        $this->originalConnection = Connections::$currentConnection;
    }

    protected function tearDown(): void
    {
        if ($this->originalConnection !== null) {
            Connections::setConnection($this->originalConnection);
        }
    }

    public function testInsertDefaultsToMySQLSetSyntax()
    {
        Connections::setConnection('tests-mysql');

        $query = (new Insert())
            ->setTable('tags')
            ->set(['`Tag` = "Linux"', '`Slug` = "linux"']);

        $this->assertEquals(
            'INSERT INTO `tags` SET `Tag` = "Linux",`Slug` = "linux"',
            (string) $query
        );
    }

    public function testStorageTypePreprocessAppliesMySQLTyping()
    {
        Connections::setConnection('tests-mysql');

        $query = (new Insert())
            ->setTable('tags')
            ->set(['`Tag` = "Linux"', '`Slug` = "linux"']);

        $this->assertEquals(
            'INSERT INTO `tags` SET `Tag` = "Linux",`Slug` = "linux"',
            testableMySQLQueryDB::preprocessPublic($query, null)
        );
    }

    public function testInsertSwitchesToSQLiteSyntaxForSQLiteConnection()
    {
        Connections::setConnection('tests-sqlite-memory');

        $query = (new Insert())
            ->setTable('tags')
            ->set(['`Tag` = "Linux"', '`Slug` = "linux"']);

        $this->assertEquals(
            'INSERT INTO `tags` (`Tag`,`Slug`) VALUES ("Linux","linux")',
            (string) $query
        );
    }

    public function testInsertSwitchesToPostgreSQLSyntaxForPostgreSQLConnection()
    {
        Connections::setConnection('tests-pgsql');

        $query = (new Insert())
            ->setTable('tags')
            ->set(['`Tag` = "Linux"', '`Slug` = "linux"']);

        $this->assertEquals(
            'INSERT INTO `tags` (`Tag`,`Slug`) VALUES ("Linux","linux")',
            (string) $query
        );
    }

    public function testStorageTypePreprocessNormalizesPostgreSQLSyntax()
    {
        Connections::setConnection('tests-pgsql');

        $this->assertEquals(
            "INSERT INTO \"tags\" (\"Tag\",\"Slug\") VALUES ('Linux','linux')",
            testablePostgreSQLQueryDB::preprocessPublic('INSERT INTO `tags` (`Tag`,`Slug`) VALUES ("Linux","linux")')
        );

        $this->assertEquals(
            'SELECT tablename AS "Tables_in_test" FROM pg_tables WHERE schemaname = current_schema() AND (tablename IN (\'fake\',\'history_fake\')) ORDER BY tablename',
            testablePostgreSQLQueryDB::preprocessPublic("SHOW TABLES WHERE `Tables_in_test` IN ('fake','history_fake')")
        );
    }
}
