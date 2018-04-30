<?php
namespace Divergence\Tests\IO\Database;

use Divergence\Tests\MockSite\App;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\TestUtils;

use PHPUnit\Framework\TestCase;

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
    public function testGetConnection() {
        TestUtils::requireDB($this);
        $this->assertInstanceOf(\PDO::class,DB::getConnection());
        $this->assertInstanceOf(\PDO::class,DB::getConnection('tests-mysql'));
        $this->assertInstanceOf(\PDO::class,DB::getConnection('tests-mysql-socket'));
        $this->expectExceptionMessage('PDO failed to connect on config "mysql" mysql:host=localhost;port=3306;dbname=divergence');
        $this->assertInstanceOf(\PDO::class,DB::getConnection('mysql'));
    }

    /**
     * @covers Divergence\IO\Database\MySQL::escape
     * 
     * I hope you had as much fun reading this code as I had writing it.
     * 
     */
    public function testEscape() {
        TestUtils::requireDB($this);

        $Connection = DB::getConnection();
        $z = function($x) use ($Connection) {
            $x = $Connection->quote($x);
            return substr($x, 1, strlen($x)-2);
        };      

        $littleBobbyTables = 'Robert\'); DROP TABLE Students;--';
        $safeLittleBobbyTables = $z($littleBobbyTables);
        
        $arrayOfBobbies = [
            'lorum ipsum',
            $littleBobbyTables,
            '; DROP tests '
        ];
        $safeArrayOfBobbies = [];

        foreach($arrayOfBobbies as $oneBob) {
            $safeArrayOfBobbies[] = $z($oneBob);
        }
        
        $this->assertEquals($safeLittleBobbyTables,DB::escape($littleBobbyTables));
        $this->assertEquals($safeArrayOfBobbies,DB::escape($arrayOfBobbies));
        $this->assertEquals(new \stdClass(),new \stdClass());
    }

    /**
     * @covers Divergence\IO\Database\MySQL::affectedRows
     * 
     */
    /*public static function testAffectedRows()
    {
        //return self::$LastStatement->rowCount();
    }*/
    
    /**
     * @covers Divergence\IO\Database\MySQL::foundRows
     * @covers Divergence\IO\Database\MySQL::oneValue
     * 
     */
    public function testFoundRows()
    {
        $tags = DB::allRecords('select SQL_CALC_FOUND_ROWS * from `tags` LIMIT 1;');
        $foundRows = DB::oneValue('SELECT FOUND_ROWS()');
        $tagsCount = DB::oneValue('SELECT COUNT(*) as `Count` FROM `tags`');
        
        $this->assertEquals($tagsCount,$foundRows);
        $this->assertEquals(count($tags),1);
    }
    
     /**
     * @covers Divergence\IO\Database\MySQL::insertID
     * @covers Divergence\IO\Database\MySQL::oneRecord
     * @covers Divergence\IO\Database\MySQL::nonQuery
     * @covers Divergence\Models\ActiveRecord::create
     * @covers Divergence\Models\ActiveRecord::save
     * 
     */
    public function testInsertID()
    {
        $expected = DB::oneRecord('SHOW TABLE STATUS WHERE name = "tags"')['Auto_increment'];
        $x = Tag::create(['Tag'=>'deleteMe','Slug'=>'deleteme'],true);
        $returned = DB::getConnection()->lastInsertId();
        $this->assertEquals($expected,$returned);
        $this->assertEquals($returned,DB::insertID());
        $x->destroy();
    }
    
    /*public function testPrepareQuery()
    {
        //return self::preprocessQuery($query, $parameters);
    }*/
}