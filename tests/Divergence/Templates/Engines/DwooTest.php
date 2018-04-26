<?php
namespace Divergence\Tests\Templates\Engines;

use Divergence\Templates\Engines\Dwoo;

use PHPUnit\Framework\TestCase;

use Dwoo\Core;

class AccesserToPrivated
{
    public function getPrivate($obj, $attribute) {
        $getter = function() use ($attribute) {return $this->$attribute;};
        return \Closure::bind($getter, $obj, get_class($obj));
    }
    
    public function setPrivate($obj, $attribute) {
        $setter = function($value) use ($attribute) {$this->$attribute = $value;};
        return \Closure::bind($setter, $obj, get_class($obj));
    }
}

class DwooTest extends TestCase
{
    public $dwoo;

    public function setUp()
    {
        $this->$dwoo = new Dwoo();
    }

    /**
     * @covers Divergence\Templates\Engines\Dwoo::__construct
     */
    public function testConstructor() {
        $this->assertInstanceOf(Core::class,$this->$dwoo);

        $accesser = new AccesserToPrivated();
        $getCompileDir = $accesser->getPrivate($this->$dwoo, 'compileDir');
        $getCacheDir = $accesser->getPrivate($this->$dwoo, 'cacheDir');

        $this->assertEquals($getCompileDir(),'/tmp/dwoo/compiled/');
        $this->assertEquals($getCacheDir(),'/tmp/dwoo/cached/');
    }
}