<?php
namespace Divergence\Tests\IO\Database;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Tests\MockSite\App;
use Divergence\IO\Database\MySQL as DB;

use Divergence\Tests\MockSite\Models\Tag;

class fakeResult  {
    public $field_count;
    public $num_rows;
}

class testableDB extends DB {
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
        return static::finishQueryLog($queryLog,$result);
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

    public static function clearConfig() {
        static::$Config = null;
    }
}

class MySQLTest extends TestCase
{
    public $ApplicationPath;

    public function setUp()
    {
        $this->ApplicationPath = realpath(__DIR__.'/../../../../');
        App::init($this->ApplicationPath);
    }

    /**
     * @covers Divergence\IO\Database\MySQL::getConnection
     */
    public function testGetConnection()
    {
        TestUtils::requireDB($this);
        $this->assertInstanceOf(\PDO::class, DB::getConnection());
        $this->assertInstanceOf(\PDO::class, DB::getConnection('tests-mysql'));
        $this->assertInstanceOf(\PDO::class, DB::getConnection('tests-mysql-socket'));
        $this->expectExceptionMessage('PDO failed to connect on config "mysql" mysql:host=localhost;port=3306;dbname=divergence');
        $this->assertInstanceOf(\PDO::class, DB::getConnection('mysql'));
    }

    /**
     * @covers Divergence\IO\Database\MySQL::escape
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
     * @covers Divergence\IO\Database\MySQL::affectedRows
     * @covers Divergence\IO\Database\MySQL::nonQuery
     *
     */
    public function testAffectedRows()
    {
        TestUtils::requireDB($this);

        DB::nonQuery('UPDATE `tags` SET `CreatorID`=1 WHERE `ID`<3');

        $this->assertEquals(2, DB::affectedRows());
    }
    
    /**
     * @covers Divergence\IO\Database\MySQL::foundRows
     * @covers Divergence\IO\Database\MySQL::oneValue
     *
     */
    public function testFoundRows()
    {
        TestUtils::requireDB($this);

        $tags = DB::allRecords('select SQL_CALC_FOUND_ROWS * from `tags` LIMIT 1;');
        $foundRows = DB::oneValue('SELECT FOUND_ROWS()');
        $tagsCount = DB::oneValue('SELECT COUNT(*) as `Count` FROM `tags`');
        
        $this->assertEquals($tagsCount, $foundRows);
        $this->assertCount(1, $tags);

        $tags = Tag::getAll(['limit'=>1,'calcFoundRows'=>true]);
        $this->assertEquals($tagsCount,DB::foundRows());

        // valid query. no records found
        $this->assertFalse(DB::oneValue('SELECT * FROM `tags` WHERE 1=0'));
    }
    
    /**
    * @covers Divergence\IO\Database\MySQL::insertID
    * @covers Divergence\IO\Database\MySQL::oneRecord
    * @covers Divergence\IO\Database\MySQL::nonQuery
    * @covers Divergence\Models\ActiveRecord::create
    * @covers Divergence\Models\ActiveRecord::save
    * @covers Divergence\Models\ActiveRecord::destroy
    *
    */
    public function testInsertID()
    {
        TestUtils::requireDB($this);

        $expected = DB::oneRecord('SHOW TABLE STATUS WHERE name = "tags"')['Auto_increment'];
        $x = Tag::create(['Tag'=>'deleteMe','Slug'=>'deleteme'], true);
        $returned = DB::getConnection()->lastInsertId();
        $this->assertEquals($expected, $returned);
        $this->assertEquals($returned, DB::insertID());
        $x->destroy();
    }

    /**
     * @covers Divergence\IO\Database\MySQL::prepareQuery
     * @covers Divergence\IO\Database\MySQL::preprocessQuery
     * @covers Divergence\Models\ActiveRecord::getByID
     * @covers Divergence\Models\ActiveRecord::getRecordByField
     * @covers Divergence\Models\ActiveRecord::instantiateRecord
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
        $this->assertEquals(vsprintf('UPDATE `%s` SET `CreatorID`=%d WHERE `ID`=%d', [
            Tag::$tableName,
            1,
            3,
        ]), $data);

        $data = DB::prepareQuery('UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=%d', 3);
        $this->assertEquals(sprintf('UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=%d', 3), $data);

        $query = 'UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=3';
        $data = DB::prepareQuery($query);
        $this->assertEquals($query, $data);

        $this->assertEquals('test',DB::prepareQuery('%s','test'));
    }

    /**
     * @covers Divergence\IO\Database\MySQL::nonQuery
     * @covers Divergence\Models\ActiveRecord::getByID
     * @covers Divergence\Models\ActiveRecord::getRecordByField
     * @covers Divergence\Models\ActiveRecord::instantiateRecord
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
     * @covers Divergence\IO\Database\MySQL::query
     * @covers Divergence\IO\Database\MySQL::handleError
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
     * @covers Divergence\IO\Database\MySQL::query
     * @covers Divergence\IO\Database\MySQL::handleError
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
     * @covers Divergence\IO\Database\MySQL::query
     * @covers Divergence\IO\Database\MySQL::handleError
     *
     */
    public function testQueryExceptionHandled()
    {
        TestUtils::requireDB($this);

        $Context = $this;
        // bad queries!
        DB::query('SELECT malformed query', null, function () use ($Context) {
            $args = func_get_args();
            $Context->assertEquals('SELECT malformed query', $args[0]);
            $Context->assertEquals(0, $args[1]);
        });
    }

    /**
     * @covers Divergence\IO\Database\MySQL::query
     * @covers Divergence\IO\Database\MySQL::handleError
     *
     */
    public function testPDOStatementError()
    {
        $this->expectExceptionMessage('Database error!');
        $Query = DB::query('SELECT * FROM `fake` WHERE (`Handle` = "Boyd")  LIMIT 1');
    }

    /**
     * @covers Divergence\IO\Database\MySQL::table
     *
     */
    public function testTable()
    {
        $y = DB::allRecords('SHOW TABLES');
        $x = DB::table('Tables_in_test','SHOW TABLES');
        foreach($y as $a) {
            $this->assertEquals($a,$x[$a['Tables_in_test']]);
        }
    }

    /**
     * @covers Divergence\IO\Database\MySQL::nonQuery
     * @covers Divergence\IO\Database\MySQL::handleError
     *
     */
    public function testNonQueryExceptionDevException()
    {
        TestUtils::requireDB($this);

        App::$Config['environment']='dev';
        DB::$defaultDevLabel = 'tests-mysql';

        $this->expectException(\RunTimeException::class);
        $this->expectExceptionMessage('Database error!');
        DB::nonQuery('SELECT malformed query');
    }

    /**
     * @covers Divergence\IO\Database\MySQL::nonQuery
     * @covers Divergence\IO\Database\MySQL::handleError
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

            $Context->assertEquals($a, $args[0]);
            $Context->assertEquals(0, $args[1]);
        });
    }


    /**
     * @covers Divergence\IO\Database\MySQL::allRecords
     *
     */
    public function testAllRecords()
    {
        $tables = DB::allRecords('SHOW TABLES');
        
        $this->assertCount(3,$tables);
        $this->assertNotEmpty($tables[0]['Tables_in_test']);
        $this->assertNotEmpty($tables[1]['Tables_in_test']);
        $this->assertNotEmpty($tables[2]['Tables_in_test']);
    }

    /**
     * @covers Divergence\IO\Database\MySQL::allValues
     *
     */
    public function testAllValues()
    {
        $tables = DB::allValues('Tables_in_test','SHOW TABLES');
        $this->assertEquals(['canaries','canaries_history','tags'],$tables);
    }

    /**
     * @covers Divergence\IO\Database\MySQL::clearCachedRecord
     *
     */
    public function testClearCachedRecord()
    {
        $query = 'SELECT * FROM `%s` WHERE `%s` = "%s" LIMIT 1';
        $params = [
            Tag::$tableName,
            'Tag',
            'Linux',
        ];
        
        $key = sprintf('%s/%s:%s', Tag::$tableName, 'Tag', 'Linux');
        $record = testableDB::oneRecordCached($key, $query, $params);
        $cache = testableDB::getProtected('_record_cache');
        $this->assertEquals($cache[$key],$record);
        testableDB::clearCachedRecord($key);
        $cache = testableDB::getProtected('_record_cache');
        $this->assertNull($cache[$key]);
    }
    
    /**
     * @covers Divergence\IO\Database\MySQL::oneRecordCached
     *
     */
    public function testOneRecordCached()
    {
        $query = 'SELECT * FROM `%s` WHERE `%s` = "%s" LIMIT 1';
        $params = [
            Tag::$tableName,
            'Tag',
            'Linux',
        ];
        
        $key = sprintf('%s/%s:%s', Tag::$tableName, 'Tag', 'Linux');
        $record = testableDB::oneRecordCached($key, $query, $params);
        $cache = testableDB::getProtected('_record_cache');
        $this->assertEquals($cache[$key],$record);
        $record = testableDB::oneRecordCached($key, $query, $params);
        $this->assertEquals($cache[$key],$record);

        // forced error
        $this->expectExceptionMessage('Database error!');
        $record = testableDB::oneRecordCached('something', 'SELECT FROM NOTHING');
    }

    /**
     * @covers Divergence\IO\Database\MySQL::preprocessQuery
     *
     */
    public function testPreprocessQuery()
    {
        $this->assertEquals('test',testableDB::_preprocessQuery('%s','test'));
        $this->assertEquals(2,testableDB::_preprocessQuery('%s',2));
    }

    /**
     * @covers Divergence\IO\Database\MySQL::startQueryLog
     *
     */
    public function testStartQueryLog()
    {
        $this->assertFalse(testableDB::_startQueryLog(null));
        App::$Config['environment'] = 'dev';
        $x = testableDB::_startQueryLog('SELECT corgies');
        $this->assertEquals('SELECT corgies', $x['query']);

        $s = explode('.',$x['time_start']);
        $this->assertLessThan(2,$s[0] - time());
    }

    /**
     * @covers Divergence\IO\Database\MySQL::finishQueryLog
     *
     */
    public function testFinishQueryLog()
    {
        App::$Config['environment'] = 'dev';
        $x = testableDB::_startQueryLog('SELECT corgies');
        usleep(5000);

        $result = new fakeResult();
        $result->field_count = 5;
        $result->num_rows = 5;

        testableDB::_finishQueryLog($x,$result);
        
        $expected_time_duration_ms = ($x['time_finish'] - $x['time_start']) * 1000;

        $this->assertEquals('SELECT corgies',$x['query']);
        $this->assertEquals(5,$x['result_fields']);
        $this->assertEquals(5,$x['result_rows']);
        $this->assertEquals($expected_time_duration_ms,$x['time_duration_ms']);
        $fake = false;
        $this->assertFalse(testableDB::_finishQueryLog($fake));
    }

    /**
     * @covers Divergence\IO\Database\MySQL::config
     *
     */
    public function testConfig()
    {
        $config = testableDB::_config();
        $this->assertEquals(testableDB::getProtected('Config'),$config);
        testableDB::clearConfig();
        $this->assertNull(testableDB::getProtected('Config'));
        $config = testableDB::_config();
        $this->assertEquals(testableDB::getProtected('Config'),$config);
    }

    /**
     * @covers Divergence\IO\Database\MySQL::getDefaultLabel
     *
     */
    public function testGetDefaultLabel()
    {
        $this->assertEquals(testableDB::$defaultProductionLabel,testableDB::_getDefaultLabel());
        App::$Config['environment'] = 'dev';
        $this->assertEquals(testableDB::$defaultDevLabel,testableDB::_getDefaultLabel());
        App::$Config['environment'] = 'nothing';
        $this->assertNull(testableDB::_getDefaultLabel());
        App::$Config['environment'] = 'production';
    }
}
