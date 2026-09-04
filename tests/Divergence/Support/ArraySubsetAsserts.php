<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Divergence\Tests\Support;

trait ArraySubsetAsserts
{
    public static function assertArraySubset(array $subset, array $array, string $message = ''): void
    {
        foreach ($subset as $key => $expectedValue) {
            self::assertArrayHasKey($key, $array, $message);

            $actualValue = $array[$key];

            if (is_array($expectedValue)) {
                self::assertIsArray($actualValue, $message);
                self::assertArraySubset($expectedValue, $actualValue, $message);
                continue;
            }

            self::assertEquals($expectedValue, $actualValue, $message);
        }
    }
}
