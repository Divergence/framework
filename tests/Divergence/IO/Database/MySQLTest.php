<?php
namespace Divergence\Tests\IO\Database;

use \Divergence\App;
use Divergence\IO\Database\MySQL as MySQL;

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
        $this->assertInstanceOf(\PDO::class,MySQL::getConnection());
        $this->assertInstanceOf(\PDO::class,MySQL::getConnection('tests-mysql'));
    }

    /**
     * @covers Divergence\IO\Database\MySQL::escape
     */
    //public function testEscape() {
        
    //}
}