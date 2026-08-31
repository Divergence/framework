<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Collections\Factory;

use Divergence\Data\Collections\Factory\Factory as BaseFactory;
use Divergence\Data\Collections\Factory\Getters\GetByField;
use Divergence\Data\Collections\Factory\Getters\GetByCriteria;
use Divergence\Models\Collections\Factory\Getters\GetAllByField;
use Divergence\Models\Collections\Factory\Getters\GetAllByCriteria;

class Factory extends BaseFactory
{
    protected function registerGetterClasses(): void
    {
        $this->getterClasses = [];

        foreach ([
            GetByField::class,
            GetAllByField::class,
            GetByCriteria::class,
            GetAllByCriteria::class,
        ] as $className) {
            $this->registerGetterClass($className);
        }
    }
}
