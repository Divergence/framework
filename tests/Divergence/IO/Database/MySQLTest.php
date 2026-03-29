<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\IO\Database;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Media\Media;
use Divergence\Tests\MockSite\App;
use Divergence\IO\Database\Connections;
use Divergence\IO\Database\MySQL as DB;
use Divergence\IO\Database\PostgreSQL;
use Divergence\IO\Database\Query\Select;
use Divergence\IO\Database\SQLite;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Models\Forum\Post;
use Divergence\Tests\MockSite\Models\Forum\Thread;
use Divergence\Tests\MockSite\Models\Forum\Category;

class fakeResult
{
    public $field_count;
    public $num_rows;
}

class testableDB extends DB
{
    public static function _preprocessQuery($query, $parameters = [])
    {
        return static::preprocessQuery($query, $parameters);
    }

    public static function _startQueryLog($query)
    {
        return static::startQueryLog($query);
    }
    public static function _finishQueryLog(&$queryLog, $result = false)
    {
        return static::finishQueryLog($queryLog, $result);
    }

    public static function _config()
    {
        return static::config();
    }

    public static function _getDefaultLabel()
    {
        return static::getDefaultLabel();
    }

    public static function getProtected($field)
    {
        return static::$$field;
    }

    public static function clearConfig()
    {
        static::$Config = null;
    }
}

class MySQLTest extends TestCase
{
    public $ApplicationPath;
    protected ?string $originalConnection = null;

    protected function isSQLite(): bool
    {
        return Connections::getConnectionType() === SQLite::class;
    }

    protected function isPostgreSQL(): bool
    {
        return Connections::getConnectionType() === PostgreSQL::class;
    }

    protected function getTables(): array
    {
        return $this->isSQLite()
            ? DB::allRecords("SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` NOT LIKE 'sqlite_%' ORDER BY `name`")
            : DB::allRecords('SHOW TABLES');
    }

    protected function getTableNameColumn(): string
    {
        return $this->isSQLite() ? 'name' : 'Tables_in_test';
    }

    public function setUp(): void
    {
        $this->originalConnection = Connections::$currentConnection;

        //$this->ApplicationPath = realpath(__DIR__.'/../../../../');
        //App::init($this->ApplicationPath);
    }

    public function tearDown(): void
    {
        if ($this->originalConnection !== null && Connections::$currentConnection !== $this->originalConnection) {
            Connections::setConnection($this->originalConnection);
        }
    }

    /**
     *
     */
    public function testGetConnection()
    {
        TestUtils::requireDB($this);
        $this->assertInstanceOf(\PDO::class, DB::getConnection());
        $this->assertInstanceOf(\PDO::class, DB::getConnection($this->isSQLite() ? 'tests-sqlite-memory' : 'tests-mysql'));

        if ($this->isSQLite()) {
            return;
        }

        try {
            $this->assertInstanceOf(\PDO::class, DB::getConnection('tests-mysql-socket'));
        } catch (\Exception $e) {
        }
        /**
         * For older MySQL message is: "PDO failed to connect on config "mysql" mysql:host=localhost;port=3306;dbname=divergence"
         * For newer MySQL message is: "SQLSTATE[HY000] [1044] Access denied for user 'divergence'@'localhost' to database 'divergence'"
         */
        #$this->expectExceptionCode(1044); // MySQL access denied
        $this->expectException(\Exception::class);
        //$this->expectExceptionMessage('PDO failed to connect on config "mysql" mysql:host=localhost;port=3306;dbname=divergence');
        $this->assertInstanceOf(\PDO::class, DB::getConnection('mysql'));
    }

    /**
     *
     */
    public function testSetConnection()
    {
        TestUtils::requireDB($this);
        Connections::setConnection($this->isSQLite() ? 'tests-sqlite-memory' : 'tests-mysql-socket');
        $this->assertEquals($this->isSQLite() ? 'tests-sqlite-memory' : 'tests-mysql-socket', Connections::$currentConnection);
        Connections::setConnection($this->isSQLite() ? 'tests-sqlite-memory' : 'tests-mysql');
    }

    /**
     *
     *
     * I hope you had as much fun reading this code as I had writing it.
     *
     */
    public function testEscape()
    {
        TestUtils::requireDB($this);

        $Connection = DB::getConnection();
        $z = function ($x) use ($Connection) {
            $x = $Connection->quote($x);
            return substr($x, 1, strlen($x)-2);
        };

        $littleBobbyTables = 'Robert\'); DROP TABLE Students;--';
        $safeLittleBobbyTables = $z($littleBobbyTables);

        $arrayOfBobbies = [
            'lorum ipsum',
            $littleBobbyTables,
            '; DROP tests ',
        ];
        $safeArrayOfBobbies = [];

        foreach ($arrayOfBobbies as $oneBob) {
            $safeArrayOfBobbies[] = $z($oneBob);
        }

        $this->assertEquals($safeLittleBobbyTables, DB::escape($littleBobbyTables));
        $this->assertEquals($safeArrayOfBobbies, DB::escape($arrayOfBobbies));
        $this->assertEquals(new \stdClass(), DB::escape(new \stdClass()));
    }

    /**
     *
     *
     *
     */
    public function testAffectedRows()
    {
        TestUtils::requireDB($this);

        DB::nonQuery('UPDATE `tags` SET `CreatorID`=1 WHERE `ID`<3');

        $this->assertEquals(2, DB::affectedRows());
    }

    /**
     *
     *
     *
     */
    public function testFoundRows()
    {
        TestUtils::requireDB($this);

        $storageClass = Connections::getConnectionType();
        $tagsCount = DB::oneValue('SELECT COUNT(*) as `Count` FROM `tags`');
        $query = (new Select())->setTable('tags')->limit('1')->calcFoundRows();
        $tags = $storageClass::allRecords((string) $query);
        $foundRows = $storageClass::foundRows();

        $this->assertCount(1, $tags);

        if ($this->isSQLite()) {
            $this->assertGreaterThanOrEqual(count($tags), (int) $foundRows);
        } else {
            $this->assertEquals($tagsCount, $foundRows);
        }

        // valid query. no records found
        $this->assertFalse(DB::oneValue('SELECT * FROM `tags` WHERE 1=0'));
    }

    /**
    *
    *
    *
    *
    *
    *
    *
    */
    public function testInsertID()
    {
        TestUtils::requireDB($this);

        $x = Tag::create(['Tag'=>'deleteMe','Slug'=>'deleteme'], true);
        $returned = DB::getConnection()->lastInsertId();
        $this->assertSame((string) $x->ID, (string) $returned);
        $this->assertEquals($returned, DB::insertID());
        $x->destroy();
    }

    /**
     *
     *
     *
     *
     *
     *
     */
    public function testPrepareQuery()
    {
        TestUtils::requireDB($this);

        $data = DB::prepareQuery('UPDATE `%s` SET `CreatorID`=%d WHERE `ID`=%d', [
            Tag::$tableName,
            1,
            3,
        ]);
        $expected = vsprintf('UPDATE `%s` SET `CreatorID`=%d WHERE `ID`=%d', [
            Tag::$tableName,
            1,
            3,
        ]);
        if ($this->isPostgreSQL()) {
            $expected = 'UPDATE "tags" SET "CreatorID"=1 WHERE "ID"=3';
        }
        $this->assertEquals($expected, $data);

        $data = DB::prepareQuery('UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=%d', 3);
        $expected = sprintf('UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=%d', 3);
        if ($this->isPostgreSQL()) {
            $expected = 'UPDATE "tags" SET "CreatorID"=1 WHERE "ID"=3';
        }
        $this->assertEquals($expected, $data);

        $query = 'UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=3';
        $data = DB::prepareQuery($query);
        $this->assertEquals($this->isPostgreSQL() ? 'UPDATE "tags" SET "CreatorID"=1 WHERE "ID"=3' : $query, $data);

        $this->assertEquals('test', DB::prepareQuery('%s', 'test'));
    }

    /**
     *
     *
     *
     *
     *
     */
    public function testNonQuery()
    {
        TestUtils::requireDB($this);

        DB::nonQuery('UPDATE `%s` SET `CreatorID`=%d WHERE `ID`=%d', [
            Tag::$tableName,
            1,
            3,
        ]);

        $Tag = Tag::getByID(3);
        $this->assertEquals(1, $Tag->CreatorID);
    }

    /**
     *
     *
     *
     */
    public function testQueryException()
    {
        TestUtils::requireDB($this);

        // bad queries!
        $this->expectException(\RunTimeException::class);
        DB::query('SELECT malformed query');
    }

    /**
     *
     *
     *
     */
    public function testQueryExceptionExistingPrimaryKey()
    {
        // should trigger error
        $Query = "INSERT INTO `tags` (`ID`, `Class`, `CreatorID`, `Tag`, `Slug`) VALUES (1, 'Divergence\\Tests\\MockSite\\Models\\Tag', 1, 'Linux', 'linux')";
        $this->expectException(\RunTimeException::class);
        DB::query($Query);
    }

    /**
     *
     *
     *
     */
    public function testQueryExceptionHandled()
    {
        TestUtils::requireDB($this);

        $Context = $this;
        // bad queries!
        DB::query('SELECT malformed query', null, function () use ($Context) {
            /**
             * $args[0] is a PDOException
             *  with message:"SQLSTATE[42S22]: Column not found: 1054 Unknown column 'malformed' i
             */
            $args = func_get_args();
            $Context->assertEquals('SELECT malformed query', $args[1]);
            $Context->assertEquals(false, $args[2]);
            $Context->expectExceptionMessageMatches('/(SQLSTATE)/');
        });
    }

    /**
     *
     *
     *
     */
    public function testPDOStatementError()
    {
        $this->expectExceptionMessageMatches('/(Database error:|SQLSTATE)/');
        $Query = DB::query('SELECT * FROM `fake` WHERE (`Handle` = "Boyd")  LIMIT 1');
    }

    /**
     *
     *
     *
     */
    public function testHandleErrorDevelopment()
    {
        App::$App->Config['environment']='dev';
        DB::$defaultDevLabel = 'tests-mysql';
        if (isset(App::$App->whoops)) {
            $this->assertInstanceOf('Whoops\Handler\PrettyPageHandler', App::$App->whoops->getHandlers()[0]);
        }
        $this->expectExceptionMessageMatches('/(Database error:|SQLSTATE|whoops must not be accessed)/i');
        $Query = DB::query('SELECT * FROM `fake` WHERE (`Handle` = "Boyd")  LIMIT 1');
        App::$App->Config['environment']='production';
    }

    /**
     *
     *
     */
    public function testTable()
    {
        $y = $this->getTables();
        $x = DB::table($this->getTableNameColumn(), $this->isSQLite() ? "SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` NOT LIKE 'sqlite_%' ORDER BY `name`" : 'SHOW TABLES');
        foreach ($y as $a) {
            $this->assertEquals($a, $x[$a[$this->getTableNameColumn()]]);
        }
    }

    /**
     *
     *
     *
     */
    public function testNonQueryExceptionDevException()
    {
        TestUtils::requireDB($this);

        App::$App->Config['environment'] = 'dev';
        DB::$defaultDevLabel = 'tests-mysql';

        $this->expectException(\RunTimeException::class);
        $this->expectExceptionMessageMatches('/(Database error:|SQLSTATE)/');
        App::$App->Config['environment'] = 'production';
        DB::nonQuery('SELECT malformed query');
    }

    /**
     *
     *
     *
     */
    public function testNonQueryHandledException()
    {
        TestUtils::requireDB($this);

        $Context = $this;
        // another bad query but this time we handle the problem
        DB::nonQuery('UPDATE `%s` SET fake`=%d WHERE `ID`=%d', [
            Tag::$tableName,
            1,
            3,
        ], function () use ($Context) {
            $args = func_get_args();

            $a = vsprintf('UPDATE `%s` SET fake`=%d WHERE `ID`=%d', [
                Tag::$tableName,
                1,
                3,
            ]);

            if ($Context->isPostgreSQL()) {
                $a = 'UPDATE "tags" SET fake"=1 WHERE ""ID"`=3';
            }

            $Context->assertEquals($a, $args[1]);
            $Context->assertEquals(0, $args[2]);
        });
    }


    /**
     *
     *
     */
    public function testAllRecords()
    {
        TestUtils::requireDB($this);

        $tables = $this->getTables();

        if ($this->isSQLite()) {
            $this->assertGreaterThanOrEqual(9, count($tables));
        } else {
            $this->assertCount(10, $tables);
        }
        foreach ($tables as $table) {
            $this->assertNotEmpty($table[$this->getTableNameColumn()]);
        }
    }

    /**
     *
     *
     */
    public function testAllValues()
    {
        TestUtils::requireDB($this);

        $tables = DB::allValues($this->getTableNameColumn(), $this->isSQLite() ? "SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` NOT LIKE 'sqlite_%' ORDER BY `name`" : 'SHOW TABLES');
        $expected = [
            Canary::$tableName,
            Canary::$historyTable,
            Category::$tableName,
            Category::$historyTable,
            Post::$tableName,
            Post::$historyTable,
            Thread::$tableName,
            Thread::$historyTable,
            Media::$tableName,
            Tag::$tableName,
        ];

        if ($this->isSQLite()) {
            foreach ([
                Canary::$tableName,
                Canary::$historyTable,
                Category::$tableName,
                Category::$historyTable,
                Post::$tableName,
                Post::$historyTable,
                Thread::$tableName,
                Thread::$historyTable,
                Tag::$tableName,
            ] as $table) {
                $this->assertContains($table, $tables);
            }

            return;
        }

        $this->assertEquals($expected, $tables);
    }

    /**
     *
     *
     */
    public function testClearCachedRecord()
    {
        TestUtils::requireDB($this);

        $query = 'SELECT * FROM `%s` WHERE `%s` = "%s" LIMIT 1';
        $params = [
            Tag::$tableName,
            'Tag',
            'Linux',
        ];

        $key = sprintf('%s/%s:%s', Tag::$tableName, 'Tag', 'Linux');
        $record = testableDB::oneRecordCached($key, $query, $params);
        $cache = testableDB::getProtected('_record_cache');
        $this->assertEquals($cache[$key], $record);
        testableDB::clearCachedRecord($key);
        $cache = testableDB::getProtected('_record_cache');
        $this->assertNull($cache[$key]);
    }

    /**
     *
     *
     */
    public function testOneRecordCached()
    {
        TestUtils::requireDB($this);

        $query = 'SELECT * FROM `%s` WHERE `%s` = "%s" LIMIT 1';
        $params = [
            Tag::$tableName,
            'Tag',
            'Linux',
        ];

        $key = sprintf('%s/%s:%s', Tag::$tableName, 'Tag', 'Linux');
        $record = testableDB::oneRecordCached($key, $query, $params);
        $cache = testableDB::getProtected('_record_cache');
        $this->assertEquals($cache[$key], $record);
        $record = testableDB::oneRecordCached($key, $query, $params);
        $this->assertEquals($cache[$key], $record);
    }

    /**
     *
     *
     *
     */
    public function testOneRecordCachedError()
    {
        TestUtils::requireDB($this);

        // forced error
        $this->expectExceptionMessageMatches('/(Database error:SQLSTATE|)/');
        $record = testableDB::oneRecordCached('something', 'SELECT FROM NOTHING');
    }

    /**
     *
     *
     */
    public function testPreprocessQuery()
    {
        TestUtils::requireDB($this);

        $this->assertEquals('test', testableDB::_preprocessQuery('%s', 'test'));
        $this->assertEquals(2, testableDB::_preprocessQuery('%s', 2));
        $this->assertEquals('nothing', testableDB::_preprocessQuery('nothing', null));
    }

    /**
     *
     *
     */
    public function testStartQueryLog()
    {
        TestUtils::requireDB($this);

        $this->assertFalse(testableDB::_startQueryLog(null));
        App::$App->Config['environment'] = 'dev';
        $x = testableDB::_startQueryLog('SELECT corgies');
        $this->assertEquals('SELECT corgies', $x['query']);

        $s = explode('.', $x['time_start']);
        $this->assertLessThan(2, $s[0] - time());
        App::$App->Config['environment'] = 'production';
    }

    /**
     *
     *
     */
    public function testFinishQueryLog()
    {
        TestUtils::requireDB($this);

        App::$App->Config['environment'] = 'dev';
        $x = testableDB::_startQueryLog('SELECT corgies');
        usleep(5000);

        $result = new fakeResult();
        $result->field_count = 5;
        $result->num_rows = 5;

        testableDB::_finishQueryLog($x, $result);

        $expected_time_duration_ms = ($x['time_finish'] - $x['time_start']) * 1000;

        $this->assertEquals('SELECT corgies', $x['query']);
        $this->assertEquals(5, $x['result_fields']);
        $this->assertEquals(5, $x['result_rows']);
        $this->assertEquals($expected_time_duration_ms, $x['time_duration_ms']);
        $fake = false;
        $this->assertFalse(testableDB::_finishQueryLog($fake));
        App::$App->Config['environment'] = 'production';
    }

    /**
     *
     *
     */
    public function testConfig()
    {
        TestUtils::requireDB($this);

        $config = testableDB::_config();
        $this->assertEquals(testableDB::getProtected('Config'), $config);
        testableDB::clearConfig();
        $this->assertNull(testableDB::getProtected('Config'));
        $config = testableDB::_config();
        $this->assertEquals(testableDB::getProtected('Config'), $config);
    }

    /**
     *
     *
     */
    public function testGetDefaultLabel()
    {
        TestUtils::requireDB($this);

        $this->assertEquals(testableDB::$defaultProductionLabel, testableDB::_getDefaultLabel());
        App::$App->Config['environment'] = 'dev';
        $this->assertEquals(testableDB::$defaultDevLabel, testableDB::_getDefaultLabel());
        App::$App->Config['environment'] = 'nothing';
        $this->assertNull(testableDB::_getDefaultLabel());
        App::$App->Config['environment'] = 'production';
    }
}
