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

use Divergence\IO\Database\Connections;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;
use Divergence\Tests\MockSite\App;
use PHPUnit\Framework\TestListener as PHPUnit_TestListener;

class TestListener implements PHPUnit_TestListener
{
    /** Absolute path to the backtrace log file. */
    public const LOG_FILE = __DIR__ . '/../../tests/test-errors.log';

    public function __construct()
    {
        // Truncate the log at the start of each run so it only holds the
        // current session's output.
        file_put_contents(self::LOG_FILE, sprintf(
            "=== PHPUnit run started %s ===\n\n",
            date('Y-m-d H:i:s')
        ));

        // Catch anything PHPUnit itself doesn't surface (fatal errors, etc.).
        self::installGlobalHandlers();
    }

    // ── Logging helper ──────────────────────────────────────────────────────

    public static function log(string $label, ?string $context, \Throwable $e): void
    {
        $entry = sprintf(
            "[%s] %s\n  %s\n  %s\n\nBACKTRACE:\n%s\n%s\n",
            date('H:i:s'),
            $label,
            $context ? "Test: {$context}" : '(no test context)',
            get_class($e) . ': ' . $e->getMessage(),
            $e->getTraceAsString(),
            str_repeat('-', 80)
        );

        file_put_contents(self::LOG_FILE, $entry, FILE_APPEND);
        fwrite(STDERR, "[test-errors.log] " . get_class($e) . ": " . $e->getMessage() . "\n");
    }

    // ── Global handler installation ─────────────────────────────────────────

    public static function installGlobalHandlers(): void
    {
        set_exception_handler(function (\Throwable $e): void {
            self::log('UNCAUGHT EXCEPTION', null, $e);
        });

        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            $e = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            self::log(sprintf('PHP ERROR (E=%d)', $errno), null, $e);
            return false; // let PHPUnit's own handler also run
        });

        register_shutdown_function(function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $e = new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
                self::log('FATAL SHUTDOWN ERROR', null, $e);
            }
        });
    }

    // ── PHPUnit listener interface ──────────────────────────────────────────

    public function addError(Test $test, \Throwable $e, float $time): void
    {
        self::log('ERROR', $test->getName(), $e);
    }

    public function addWarning(Test $test, \PHPUnit\Framework\Warning $e, float $time): void
    {
        self::log('WARNING', $test->getName(), $e);
    }

    public function addFailure(Test $test, \PHPUnit\Framework\AssertionFailedError $e, float $time): void
    {
        self::log('FAILURE', $test->getName(), $e);
    }

    public function addIncompleteTest(Test $test, \Throwable $e, float $time): void
    {
        self::log('INCOMPLETE', $test->getName(), $e);
    }

    public function addRiskyTest(Test $test, \Throwable $e, float $time): void
    {
        self::log('RISKY', $test->getName(), $e);
    }

    public function addSkippedTest(Test $test, \Throwable $e, float $time): void
    {
        // Skips are usually intentional; log only if there's a real message.
        if ($e->getMessage() !== '') {
            //self::log('SKIPPED', $test->getName(), $e);
        }
    }

    public function startTest(Test $test): void {}

    public function endTest(Test $test, float $time): void {}

    public function startTestSuite(TestSuite $suite): void
    {
        if ($connectionLabel = $this->getConnectionLabel($suite)) {
            try {
                $_SERVER['REQUEST_URI'] = '/';
                $suite->app = new App(__DIR__.'/../../');
                $suite->connectionLabel = $connectionLabel;
                Connections::setConnection($suite->connectionLabel);
                $suite->app->setUp();
            } catch (\Throwable $e) {
                self::log('SUITE SETUP ERROR', $suite->getName(), $e);

                if (isset($suite->app) && isset($suite->app->whoops)) {
                    $suite->app->whoops->handleException($e);
                }

                throw $e;
            }

            fwrite(STDERR, sprintf('Starting Divergence Mock Environment for PHPUnit (%s)', $suite->connectionLabel)."\n");
        }
    }

    public function endTestSuite(TestSuite $suite): void
    {
        if ($this->getConnectionLabel($suite)) {
            if (isset($suite->app)) {
                $suite->app->tearDown();
            }

            fwrite(STDERR, "\n".sprintf('Cleaning up Divergence Mock Environment for PHPUnit (%s)', $suite->connectionLabel ?? 'unknown')."\n");
        }
    }

    protected function getConnectionLabel(TestSuite $suite): ?string
    {
        return match ($suite->getName()) {
            'tests-mysql', 'tests-sqlite-memory' => getenv('DIVERGENCE_TEST_DB') ?: $suite->getName(),
            default => null,
        };
    }
}
