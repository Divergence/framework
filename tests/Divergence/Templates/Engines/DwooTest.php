<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Templates\Engines;

use Dwoo\Core;

use PHPUnit\Framework\TestCase;

use Divergence\Templates\Engines\Dwoo;

class AccesserToPrivated
{
    public function getPrivate($obj, $attribute)
    {
        $getter = function () use ($attribute) {
            return $this->$attribute;
        };
        return \Closure::bind($getter, $obj, get_class($obj));
    }

    public function setPrivate($obj, $attribute)
    {
        $setter = function ($value) use ($attribute) {
            $this->$attribute = $value;
        };
        return \Closure::bind($setter, $obj, get_class($obj));
    }
}
