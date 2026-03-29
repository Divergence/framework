<?php

namespace Divergence\Models\Factory\Getters;

class GetAllByWhere extends ModelGetter
{
    public function getAllByWhere($conditions = [], $options = [])
    {
        return $this->instantiateRecords($this->factory->getAllRecordsByWhere($conditions, $options));
    }
}
