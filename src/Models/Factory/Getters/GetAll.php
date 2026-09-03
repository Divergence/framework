<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetAll extends ModelGetter
{
    public function getAll($options = [])
    {
        return $this->instantiateRecords($this->factory->getAllRecords($options));
    }
}
