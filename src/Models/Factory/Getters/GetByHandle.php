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

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByHandle extends ModelGetter
{
    public function getByHandle($handle)
    {
        $handleField = $this->getHandleFieldName();

        if ($this->fieldExists($handleField)) {
            if ($Record = $this->factory->getByField($handleField, $handle)) {
                return $Record;
            }
        }

        if (!is_int($handle) && !(is_string($handle) && ctype_digit($handle))) {
            return null;
        }

        return $this->factory->getByID($handle);
    }
}
