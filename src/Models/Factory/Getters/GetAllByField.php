<?php

namespace Divergence\Models\Factory\Getters;

class GetAllByField extends ModelGetter
{
    public function getAllByField($field, $value, $options = [])
    {
        return $this->factory->getAllByWhere([$field => $value], $options);
    }
}
