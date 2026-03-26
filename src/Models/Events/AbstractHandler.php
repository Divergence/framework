<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Events;

use Divergence\IO\Database\Connections;
use ReflectionMethod;
use ReflectionProperty;

abstract class AbstractHandler
{
    /**
     * @var array<string, object>
     */
    protected static $storageInstances = [];

    protected static function getStorage()
    {
        $storageClass = Connections::getConnectionType();

        if (!isset(static::$storageInstances[$storageClass])) {
            static::$storageInstances[$storageClass] = new $storageClass();
        }

        return static::$storageInstances[$storageClass];
    }

    protected static function getWriterClass(): string
    {
        $storageClass = Connections::getConnectionType();

        return match ($storageClass) {
            \Divergence\IO\Database\SQLite::class => \Divergence\IO\Database\Writer\SQLite::class,
            default => \Divergence\IO\Database\Writer\MySQL::class,
        };
    }

    protected static function getProperty($subject, string $property)
    {
        $reflection = new ReflectionProperty($subject, $property);

        return $reflection->getValue($subject);
    }

    protected static function setProperty($subject, string $property, $value): void
    {
        $reflection = new ReflectionProperty($subject, $property);
        $reflection->setValue($subject, $value);
    }

    protected static function getStaticProperty(string $className, string $property)
    {
        $reflection = new ReflectionProperty($className, $property);

        return $reflection->getValue();
    }

    protected static function callMethod($subject, string $method, array $arguments = [])
    {
        $reflection = new ReflectionMethod($subject, $method);

        return $reflection->invokeArgs($subject, $arguments);
    }

    protected static function callStaticMethod(string $className, string $method, array $arguments = [])
    {
        $reflection = new ReflectionMethod($className, $method);

        return $reflection->invokeArgs(null, $arguments);
    }
}
