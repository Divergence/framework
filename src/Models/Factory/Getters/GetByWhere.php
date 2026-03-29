<?php

namespace Divergence\Models\Factory\Getters;

class GetByWhere extends ModelGetter
{
    public function getByWhere($conditions, $options = [])
    {
        return $this->instantiateRecord($this->factory->getRecordByWhere($conditions, $options));
    }
}
