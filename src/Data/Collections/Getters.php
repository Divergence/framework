<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Data\Collections;

use Error;
use Divergence\Data\Collections\Factory\Factory;

trait Getters
{
    protected static $_registeredGetterMethods = [];

    public static function Factory(): Factory
    {
        return new Factory();
    }

    protected static function registerGetterMethods(): void
    {
        $factory = static::Factory();

        static::$_registeredGetterMethods[static::class] = $factory->getGetterClasses();
    }

    public static function __callStatic(string $name, array $arguments)
    {
        $factory = static::Factory();

        if (method_exists($factory, $name)) {
            return $factory->$name(...$arguments);
        }

        throw new Error(sprintf('Call to undefined method %s::%s()', static::class, $name));
    }

    public function __call(string $name, array $arguments)
    {
        if (!isset(static::$_registeredGetterMethods[static::class])) {
            static::registerGetterMethods();
        }

        $methodName = strtolower($name);
        $getterClass = static::$_registeredGetterMethods[static::class][$methodName] ?? null;

        if ($getterClass === null) {
            throw new Error(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        return $getterClass::handle($this, ...$arguments);
    }
}
