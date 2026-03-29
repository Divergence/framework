<?php

namespace Divergence\Models\Factory\Getters;

class GetByField extends ModelGetter
{
    public function getByField($field, $value, $cacheIndex = false)
    {
        return $this->instantiateRecord($this->factory->getRecordByField($field, $value, $cacheIndex));
    }
}
