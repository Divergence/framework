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

    public function testConstructorStoresKeysWithoutCalculatingHash(): void
    {
        $hasher = new KeyToHashInt(['key']);

        $this->assertSame(['key'], $hasher->keys);
        $this->assertNull($hasher->hash);
    }

    public function testSingularIntegerIsReturnedAsIs(): void
    {
        $hasher = new KeyToHashInt([123]);

        $this->assertSame(123, $hasher->getSingular());
        $this->assertSame(123, $hasher->hash);
    }

    public function testSingularNumericStringIsConvertedToInteger(): void
    {
        $hasher = new KeyToHashInt(['123']);

        $this->assertSame(123, $hasher->getSingular());
        $this->assertSame(123, $hasher->hash);
    }

    public function testSingularNonNumericStringIsHashed(): void
    {
        $hasher = new KeyToHashInt(['hello']);
        $expected = intval(hexdec(hash('xxh64', 'hello')));

        $this->assertSame($expected, $hasher->getSingular());
        $this->assertSame($expected, $hasher->hash);
    }

    public function testUnsupportedSingularKeyReturnsNull(): void
    {
        $hasher = new KeyToHashInt([null]);

        $this->assertNull($hasher->getSingular());
        $this->assertNull($hasher->hash);
    }

    public function testGetReturnsPreviouslyCalculatedHash(): void
    {
        $hasher = new KeyToHashInt([123]);

        $this->assertSame(123, $hasher->get());
        $hasher->keys = [456];
        $this->assertSame(123, $hasher->get());
    }

    public function testGetPacksTwoKeysIntoOneInteger(): void
    {
        $keys = ['left', 'right'];
        $expected = crc32($keys[0]) << 32 | crc32($keys[1]);
        $hasher = new KeyToHashInt($keys);

        $this->assertSame($expected, $hasher->get());
        $this->assertSame($expected, $hasher->hash);
    }

    public function testGetHashesThreeOrMoreKeysTogether(): void
    {
        $keys = ['tenant', 'record', 'locale'];
        $expected = intval(hexdec(hash('xxh64', implode('|', $keys))));
        $hasher = new KeyToHashInt($keys);

        $this->assertSame($expected, $hasher->get());
        $this->assertSame($expected, $hasher->hash);
    }
}
