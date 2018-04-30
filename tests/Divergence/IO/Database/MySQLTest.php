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
        $littleBobbyTables = 'Robert\'); DROP TABLE Students;--';
        dump(DB::escape([
            'lorum ipsum',
            $littleBobbyTables,
            ''
        ]));
        dump(DB::escape($littleBobbyTables));
    }
}