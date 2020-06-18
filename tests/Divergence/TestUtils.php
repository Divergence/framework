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

use Divergence\IO\Database\MySQL as DB;

class TestUtils
{
    public static function requireDB(TestCase $ctx)
    {
        try {
            DB::getConnection();
        } catch (\Exception $e) {
            $ctx->markTestSkipped('Setup a MySQL database connection to a local MySQL server.');
        }
    }
}
