<?php

namespace Divergence\Models\Factory\Getters;

class GetAllByClass extends ModelGetter
{
    public function getAllByClass($className = false, $options = [])
    {
        return $this->factory->getAllByField('Class', $className ? $className : $this->getModelClass(), $options);
    }
}
