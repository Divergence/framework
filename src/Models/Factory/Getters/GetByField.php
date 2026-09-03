<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByField extends ModelGetter
{
    public function getByField($field, $value, $cacheIndex = false)
    {
        return $this->instantiateRecord($this->factory->getRecordByField($field, $value, $cacheIndex));
    }
}
