<?php
namespace Divergence\Tests\IO\Database;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Tests\MockSite\App;
use Divergence\IO\Database\MySQL as DB;

use Divergence\Tests\MockSite\Models\Tag;

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
        $this->assertEquals(new \stdClass(), new \stdClass());
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
        $this->assertEquals(count($tags), 1);
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

        $data = DB::prepareQuery($query = 'UPDATE `tags` SET `CreatorID`=1 WHERE `ID`=3');
        $this->assertEquals($query, $data);
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
}
