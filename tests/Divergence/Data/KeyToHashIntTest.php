<?php

namespace Divergence\Tests\Data;

use Divergence\Data\KeyToHashInt;
use PHPUnit\Framework\TestCase;

class KeyToHashIntTest extends TestCase
{
    protected function tearDown(): void
    {
        KeyToHashInt::$singleton = null;
    }

    public function testHashForKeysReusesSingletonWithoutReusingHash(): void
    {
        $this->assertSame(123, KeyToHashInt::hashForKeys([123]));
        $singleton = KeyToHashInt::$singleton;

        $this->assertSame(456, KeyToHashInt::hashForKeys([456]));
        $this->assertSame($singleton, KeyToHashInt::$singleton);
    }
}
