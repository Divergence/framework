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

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Models\Auth\Session;

class SessionTest extends TestCase
{
    public static ?string $sessionHandle;

    public function testGenerateUniqueHandle()
    {
        $this->assertEquals(32, strlen(Session::generateUniqueHandle()));
        $this->assertNotEquals(Session::generateUniqueHandle(), Session::generateUniqueHandle());
    }

    /**
     * Warning:
     * Session uses register_shutdown_function to save()
     * We must manually run save if we expect values in the DB
     *
     * @return void
     */
    public function testGetFromRequest()
    {
        // blank new session from 127.0.0.1
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $createdTime = date('U');
        $session = Session::getFromRequest();
        
        $this->assertEquals($createdTime, $session->Created);
        $this->assertEquals(inet_pton($_SERVER['REMOTE_ADDR']), $session->LastIP);
        $this->assertEquals(32, strlen($session->Handle));
        $session->save();

        $key = sprintf('%s/%s:%s', Session::$tableName, 'Handle', $session->Handle);
        DB::clearCachedRecord($key);

        $checkGetByHandle = Session::GetByHandle($session->Handle);
        $this->assertEquals($session->Handle, $checkGetByHandle->Handle);

        // set our cookie :)
        static::$sessionHandle = $session->Handle;
        $lastRequest = date('U');
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_COOKIE[Session::$cookieName] = static::$sessionHandle;
        $new = Session::getFromRequest();
        $this->assertEquals($createdTime, $new->Created);
        $this->assertEquals(inet_pton($_SERVER['REMOTE_ADDR']), $new->LastIP);
        $this->assertEquals($lastRequest, $new->LastRequest);
        $this->assertEquals(1, $new->ID);

        // now $_REQUEST
        static::$sessionHandle = $session->Handle;
        $lastRequest = date('U');
        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        unset($_COOKIE[Session::$cookieName]);
        $_REQUEST[Session::$cookieName] = static::$sessionHandle;
        $new = Session::getFromRequest();
        $this->assertEquals($createdTime, $new->Created);
        $this->assertEquals(inet_pton($_SERVER['REMOTE_ADDR']), $new->LastIP);
        $this->assertEquals($lastRequest, $new->LastRequest);
        $this->assertEquals(1, $new->ID);
    }
}
