<?php

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
