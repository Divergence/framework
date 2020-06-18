<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests\Models\Auth;

use PHPUnit\Framework\TestCase;
use Divergence\Models\Auth\Session;

class SessionTest extends TestCase
{
    public function testGenerateUniqueHandle()
    {
        $this->assertEquals(32, strlen(Session::generateUniqueHandle()));
        $this->assertNotEquals(Session::generateUniqueHandle(), Session::generateUniqueHandle());
    }
}
