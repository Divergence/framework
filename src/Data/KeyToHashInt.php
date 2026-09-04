<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data;

/**
 * Best effort Primary Key bit packed into a PHP INT
 */
class KeyToHashInt
{
    public static ?self $singleton = null;

    public array $keys;
    public ?int $hash = null;

    public function __construct(array $keys)
    {
        $this->keys = $keys;
        $this->hash = null;
    }

    public function getSingular()
    {
        // If a single key PK is already an int, return it as-is
        if (is_int($this->keys[0])) {
            $this->hash = $this->keys[0];
            return $this->hash;
        }

        if (is_string($this->keys[0])) {
            // if for some reason it's a string we're gonna convert it to an int
            if (ctype_digit($this->keys[0])) {
                $this->hash = (int)($this->keys[0]);
                return $this->hash;
                // if it's a non-numeric string but still being used as a PK then we'll hash it for a 64 bit int
            } else {
                $this->hash = intval(hexdec(hash('xxh64', $this->keys[0])));
                return $this->hash;
            }
        }

        return $this->hash;
    }

    // Pack two 32-bit component hashes into one 64-bit integer.
    public function getDouble()
    {
        $k1 = crc32((string) $this->keys[0]);
        $k2 = crc32((string) $this->keys[1]);
        // shift first hash left 32 bits and OR with second
        $this->hash = $k1 << 32 | $k2;
        return $this->hash;
    }

    // Three or more dimensions use one delimited xxHash64 input.
    public function getMany()
    {
        $this->hash = intval(hexdec(hash('xxh64', implode('|', $this->keys))));
        return $this->hash;
    }

    public function get()
    {
        if ($this->hash !== null) {
            return $this->hash;
        }

        switch (count($this->keys)) {
            case 1:
                return $this->getSingular();

            case 2:
                return $this->getDouble();

            default:
                return $this->getMany();
        }
    }

    public static function hashForKeys($keys)
    {
        if (self::$singleton === null) {
            self::$singleton = new self($keys);
        } else {
            self::$singleton->keys = $keys;
            self::$singleton->hash = null;
        }

        return self::$singleton->get();
    }
}
