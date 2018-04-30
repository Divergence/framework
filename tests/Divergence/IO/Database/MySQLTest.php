<?php
namespace Divergence\Tests\IO\Database;

use Divergence\App;
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
        
        // initiate pdo and abort all tests
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
    }
}