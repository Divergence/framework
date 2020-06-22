<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests\Helpers;

use Divergence\Helpers\Util;

use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    /**
     *
     */
    public function testPrepareOptions()
    {
        $A = [1,6,9];
        $B = ['hello','world'];
        $this->assertEquals(Util::prepareOptions($A, $B), array_merge($B, $A));
        $this->assertEquals(Util::prepareOptions(json_encode($A), $B), array_merge($B, $A));
    }
}
