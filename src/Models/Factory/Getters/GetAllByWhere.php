<?php

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
