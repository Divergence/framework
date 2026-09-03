<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByWhere extends ModelGetter
{
    public function getByWhere($conditions, $options = [])
    {
        return $this->instantiateRecord($this->factory->getRecordByWhere($conditions, $options));
    }
}
