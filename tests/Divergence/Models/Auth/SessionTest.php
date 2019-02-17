<?php
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
