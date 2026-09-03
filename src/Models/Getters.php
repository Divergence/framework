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

use Error;

/**
 * @require-extends \Divergence\Models\ActiveRecord
 * @mixin \Divergence\Models\ActiveRecord
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

        throw new Error(sprintf('Call to undefined method %s::%s()', static::class, $name));
    }
}
