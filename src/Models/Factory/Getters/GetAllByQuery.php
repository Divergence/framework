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
class GetAllByQuery extends ModelGetter
{
    public function getAllByQuery($query, $params = [])
    {
        return $this->instantiateRecords($this->getStorage()->allRecords($query, $params, $this->getHandleExceptionCallback()));
    }
}
