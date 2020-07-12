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
use Divergence\Tests\MockSite\App;

class AppTest extends TestCase
{
    public App $App;
    public $ApplicationPath;

    public function setUp()
    {
        $this->ApplicationPath = realpath(__DIR__.'/../../');

        // if tests got killed mid way through we might have .temp files left over
        if (file_exists($this->ApplicationPath.'/.debug.temp')) {
            rename($this->ApplicationPath.'/.debug.temp', $this->ApplicationPath.'/.debug');
        }

        if (file_exists($this->ApplicationPath.'/.dev.temp')) {
            rename($this->ApplicationPath.'/.dev.temp', $this->ApplicationPath.'/.dev');
        }

        // move existing .debug and .dev files out the way during the duration of the test if they exist
        // this way we can test to make sure a production and debug environments load correctly
        if (file_exists($this->ApplicationPath.'/.debug')) {
            rename($this->ApplicationPath.'/.debug', $this->ApplicationPath.'/.debug.temp');
        }

        if (file_exists($this->ApplicationPath.'/.dev')) {
            rename($this->ApplicationPath.'/.dev', $this->ApplicationPath.'/.dev.temp');
        }
    }

    protected function tearDown()
    {
        // move .dev and .debug files that already existed at the beginning of the test back to their original states
        if (file_exists($this->ApplicationPath.'/.debug.temp')) {
            rename($this->ApplicationPath.'/.debug.temp', $this->ApplicationPath.'/.debug');
        }

        if (file_exists($this->ApplicationPath.'/.dev.temp')) {
            rename($this->ApplicationPath.'/.dev.temp', $this->ApplicationPath.'/.dev');
        }
    }

    public function createFakeDevEnv()
    {
        touch($this->ApplicationPath.'/.debug');
        touch($this->ApplicationPath.'/.dev');
    }

    public function cleanFakeDevEnv()
    {
        unlink($this->ApplicationPath.'/.debug');
        unlink($this->ApplicationPath.'/.dev');
    }

    public function doInit()
    {
        $this->App = new App($this->ApplicationPath);
        $this->App->Config = [
            'environment' => 'production',
            'debug' => false,
        ];
        //$this->App->init($this->ApplicationPath);
    }

    public function testAppInit()
    {
        $this->doInit();
        $OriginalEnvState = $this->App->config('app');
        $this->assertEquals(realpath(__DIR__.'/../../'), $this->App->ApplicationPath);
        $this->assertEquals($OriginalEnvState['debug'], $this->App->Config['debug']);
        $this->assertEquals($OriginalEnvState['environment'], $this->App->Config['environment']);
        $this->assertEquals($this->App->config('app'), $this->App->Config);
    }

    public function testAppInitException()
    {
        $original = $this->ApplicationPath;
        $this->ApplicationPath = 'fake';
        $this->expectException('Exception');
        $this->doInit();
        $this->ApplicationPath = $original;
        $this->doInit();
    }

    public function testAppConfig()
    {
        $this->doInit();
        $this->createFakeDevEnv();
        $envState = $this->App->config('app');
        $this->assertEquals($envState['debug'], true, '$this->App->config[\'debug\'] was set to false despite presence of .debug file in Application root.');
        $this->assertEquals($envState['environment'], 'dev', '$this->App->config[\'environment\'] was set to production despite presence of .dev file in Application root.');
        $this->cleanFakeDevEnv();
    }

    public function testAppRegisterErrorHandler()
    {
        $this->doInit();
        $this->assertEquals(error_reporting(), 0, 'Error reporting not set to 0 for production environment.');
        $this->createFakeDevEnv();
        $this->doInit();
        $this->assertInstanceOf(\Whoops\Run::class, $this->App->whoops, "Whoops isn't being properly registered when \$config['environment'] is set to 'dev'.");
        $this->cleanFakeDevEnv();
    }
}
