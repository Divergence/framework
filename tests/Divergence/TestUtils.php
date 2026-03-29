<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests;

use PHPUnit\Framework\TestCase;

use Divergence\IO\Database\Connections;

class TestUtils
{
    public static function getStorage()
    {
        $storageClass = Connections::getConnectionType();

        return new $storageClass();
    }

    public static function requireDB(TestCase $ctx)
    {
        try {
            static::getStorage()->getConnection();
        } catch (\Exception $e) {
            $ctx->markTestSkipped('Setup a supported database connection for the active test backend.');
        }
    }
}
