<?php
namespace Divergence\Tests;

use Divergence\IO\Database\MySQL as DB;

use PHPUnit\Framework\TestCase;

class TestUtils {
    public static function requireDB(TestCase $ctx) {
        try {
            DB::getConnection();
        }
        catch(\Exception $e) {
            $ctx->markTestSkipped('Setup a MySQL database connection to a local MySQL server.');
        }
    }
}