<?php
namespace Divergence\Tests\Helpers;

use Divergence\Helpers\Util;

use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    /**
     * @covers Divergence\Helpers\Util::prepareOptions
     */
    public function testPrepareOptions()
    {
        $A = [1,6,9];
        $B = ['hello','world'];
        $this->assertEquals(Util::prepareOptions($A, $B), array_merge($B, $A));
        $this->assertEquals(Util::prepareOptions(json_encode($A), $B), array_merge($B, $A));
    }
}
