<?php

namespace Divergence\Models\Factory\Getters;

class GetByID extends ModelGetter
{
    public function getByID($id)
    {
        return $this->instantiateRecord($this->factory->getRecordByField($this->getPrimaryKeyName(), $id, true));
    }
}
