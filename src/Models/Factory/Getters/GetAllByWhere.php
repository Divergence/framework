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
class GetAllByWhere extends ModelGetter
{
    public function getAllByWhere($conditions = [], $options = [])
    {
        return $this->instantiateRecords($this->factory->getAllRecordsByWhere($conditions, $options));
    }
}
