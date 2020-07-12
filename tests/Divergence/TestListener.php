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

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use \Divergence\IO\Database\MySQL;

use \Divergence\Tests\MockSite\App;
use PHPUnit\Framework\TestListener as PHPUnit_TestListener;

class TestListener implements PHPUnit_TestListener
{
    public function __construct()
    {
    } // does nothing but throws an error if not here

    public function addError(Test $test, \Throwable $e, float $time): void
    {
        //printf("Error while running test '%s'.\n", $test->getName());
    }

    public function addWarning(Test $test, \PHPUnit\Framework\Warning $e, float $time): void
    {
        //printf("Warning while running test '%s'.\n", $test->getName());
    }


    public function addFailure(Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void
    {
        //printf("Test '%s' failed.\n", $test->getName());
    }

    public function addIncompleteTest(Test $test, \Throwable $e, float $time): void
    {
        //printf("Test '%s' is incomplete.\n", $test->getName());
    }

    public function addRiskyTest(Test $test, \Throwable $e, float $time): void
    {
        //printf("Test '%s' is deemed risky.\n", $test->getName());
    }

    public function addSkippedTest(Test $test, \Throwable $e, float $time): void
    {
        //printf("Test '%s' has been skipped.\n", $test->getName());
    }

    public function startTest(Test $test): void
    {
        //printf("Test '%s' started.\n", $test->getName());
    }

    public function endTest(Test $test, float $time): void
    {
        //printf("Test '%s' ended.\n", $test->getName());
    }

    public function startTestSuite(TestSuite $suite): void
    {
        //printf("TestSuite '%s' started.\n", $suite->getName());
        if ($suite->getName() == 'all') {
            $_SERVER['REQUEST_URI'] = '/';
            $suite->app = new App(__DIR__.'/../../');
            MySQL::setConnection('tests-mysql');
            $suite->app->setUp();
            fwrite(STDERR, 'Starting Divergence Mock Environment for PHPUnit'."\n");
        }
    }

    public function endTestSuite(TestSuite $suite): void
    {
        //printf("TestSuite '%s' ended.\n", $suite->getName());
        if ($suite->getName() == 'all') {
            exec(sprintf('rm -rf %s', App::$App->ApplicationPath.'/media'));
            fwrite(STDERR, "\n".'Cleaning up Divergence Mock Environment for PHPUnit'."\n");
        }
    }
}
