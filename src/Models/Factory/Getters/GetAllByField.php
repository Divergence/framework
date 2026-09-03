<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetAllByField extends ModelGetter
{
    public function getAllByField($field, $value, $options = [])
    {
        return $this->factory->getAllByWhere([$field => $value], $options);
    }
}
