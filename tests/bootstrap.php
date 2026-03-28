<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Divergence\IO\Database\Connections;
use Divergence\Tests\MockSite\App;

$logFile = __DIR__ . '/test-errors.log';

file_put_contents($logFile, sprintf(
    "=== PHPUnit run started %s ===\n\n",
    date('Y-m-d H:i:s')
));

$logThrowable = static function (string $label, ?string $context, Throwable $e) use ($logFile): void {
    $entry = sprintf(
        "[%s] %s\n  %s\n  %s\n\nBACKTRACE:\n%s\n%s\n",
        date('H:i:s'),
        $label,
        $context ? "Test: {$context}" : '(no test context)',
        get_class($e) . ': ' . $e->getMessage(),
        $e->getTraceAsString(),
        str_repeat('-', 80)
    );

    file_put_contents($logFile, $entry, FILE_APPEND);
    fwrite(STDERR, "[test-errors.log] " . get_class($e) . ': ' . $e->getMessage() . "\n");
};

set_exception_handler(static function (Throwable $e) use ($logThrowable): void {
    $logThrowable('UNCAUGHT EXCEPTION', null, $e);
});

set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use ($logThrowable): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
    $logThrowable(sprintf('PHP ERROR (E=%d)', $errno), null, $e);

    return false;
});

$bootstrapApp = null;
$connectionLabel = getenv('DIVERGENCE_TEST_DB') ?: null;

if ($connectionLabel !== null && $connectionLabel !== '') {
    $_SERVER['REQUEST_URI'] = '/';
    $bootstrapApp = new App(__DIR__ . '/../');
    Connections::setConnection($connectionLabel);
    $bootstrapApp->setUp();

    fwrite(STDERR, sprintf('Starting Divergence Mock Environment for PHPUnit (%s)', $connectionLabel) . "\n");
}

register_shutdown_function(static function () use (&$bootstrapApp, $connectionLabel, $logThrowable): void {
    $err = error_get_last();

    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $e = new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
        $logThrowable('FATAL SHUTDOWN ERROR', null, $e);
    }

    if ($bootstrapApp instanceof App) {
        $bootstrapApp->tearDown();
        fwrite(STDERR, "\n" . sprintf('Cleaning up Divergence Mock Environment for PHPUnit (%s)', $connectionLabel ?? 'unknown') . "\n");
    }
});
