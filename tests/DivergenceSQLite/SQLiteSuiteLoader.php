<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * SQLite suite generator using reflection + eval.
 *
 * PHPUnit loads this file via <file> in the tests-sqlite-memory testsuite.
 *
 * Strategy:
 *   1. Require every PHP file under tests/Divergence so all test classes are
 *      declared.
 *   2. Use ReflectionClass to find every concrete TestCase subclass whose
 *      source file lives inside that directory.
 *   3. For each such class, eval() a thin named subclass in the
 *      Divergence\Tests\SQLite\* namespace.  Because the generated class has a
 *      *different* fully-qualified name, PHPUnit counts it as a separate suite
 *      and runs the tests a second time under the SQLite connection.
 */

use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\TestCase;

$divergenceTestDir = __DIR__ . '/../Divergence';
$realDivDir        = realpath($divergenceTestDir);

// ── 1. Load all files under tests/Divergence ─────────────────────────────────
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($divergenceTestDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() === 'php') {
        require_once $file->getRealPath();
    }
}

// ── 2. Collect concrete TestCase subclasses from tests/Divergence ────────────
$testClasses = [];
foreach (get_declared_classes() as $class) {
    if (!is_subclass_of($class, TestCase::class)) {
        continue;
    }

    $ref      = new ReflectionClass($class);
    $filename = $ref->getFileName();

    if ($filename === false || $ref->isAbstract()) {
        continue;
    }

    if (strpos(realpath($filename), $realDivDir) !== 0) {
        continue;
    }

    $testClasses[] = $class;
}

// ── 3. eval() a named subclass in Divergence\Tests\SQLite\* for each class ───
$suite = new TestSuite('tests-sqlite-memory');

foreach ($testClasses as $originalClass) {
    // e.g. Divergence\Tests\Models\ActiveRecordTest
    //   => namespace  Divergence\Tests\SQLite\Models
    //      class      ActiveRecordTest
    $newFQCN = preg_replace(
        '/^Divergence\\\\Tests\\\\/',
        'Divergence\\Tests\\SQLite\\',
        $originalClass
    );

    if (!class_exists($newFQCN, false)) {
        // Split into namespace + short class name for the eval'd declaration
        $lastBackslash = strrpos($newFQCN, '\\');
        $newNamespace  = substr($newFQCN, 0, $lastBackslash);
        $newShortClass = substr($newFQCN, $lastBackslash + 1);

        // eval a thin named subclass; backslash-prefix the parent FQCN
        eval('namespace ' . $newNamespace . '; class ' . $newShortClass . ' extends \\' . $originalClass . ' {}');
    }

    $suite->addTestSuite(new ReflectionClass($newFQCN));
}

return $suite;
