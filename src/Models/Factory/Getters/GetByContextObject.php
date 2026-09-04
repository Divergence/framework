<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Factory\Getters;

use Divergence\Models\ActiveRecord;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByContextObject extends ModelGetter
{
    public function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->factory->getByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }
}
