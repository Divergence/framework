<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetAllByClass extends ModelGetter
{
    public function getAllByClass($className = false, $options = [])
    {
        return $this->factory->getAllByField('Class', $className ? $className : $this->getModelClass(), $options);
    }
}
