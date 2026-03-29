<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models;

use BadMethodCallException;

/**
 * @property string $handleField Defined in the model
 * @property string $primaryKey Defined in the model
 * @property string $tableName Defined in the model
 */
trait Getters
{
    /**
     * @var array<string, array<string, bool>>
     */
    protected static $_registeredGetterMethods = [];

    /**
     * @return Factory<static>
     */
    public static function Factory(?string $modelClass = null): Factory
    {
        return new Factory($modelClass ?: static::class);
    }

    protected static function registerGetterMethods(): void
    {
        $factory = static::Factory();

        static::$_registeredGetterMethods[static::class] = array_fill_keys(
            array_map('strtolower', array_keys($factory->getGetterClasses())),
            true
        );
    }

    public static function __callStatic(string $name, array $arguments)
    {
        if (!isset(static::$_registeredGetterMethods[static::class])) {
            static::registerGetterMethods();
        }

        $factory = static::Factory();
        $methodName = strtolower($name);

        if (isset(static::$_registeredGetterMethods[static::class][$methodName]) || method_exists($factory, $name)) {
            return $factory->$name(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
    }
}
