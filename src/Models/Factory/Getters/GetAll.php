<?php

namespace Divergence\Models\Factory\Getters;

class GetAll extends ModelGetter
{
    public function getAll($options = [])
    {
        return $this->instantiateRecords($this->factory->getAllRecords($options));
    }
}
