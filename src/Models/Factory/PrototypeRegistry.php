<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Factory;

class PrototypeRegistry
{
    /**
     * @var array
     */
    protected static $prototypes = [];

    public function get(string $className, callable $factory)
    {
        if (!isset(static::$prototypes[$className])) {
            static::$prototypes[$className] = $factory();
        }

        return static::$prototypes[$className];
    }
}
