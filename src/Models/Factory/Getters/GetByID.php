<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByID extends ModelGetter
{
    public function getByID($id)
    {
        return $this->instantiateRecord($this->factory->getRecordByField($this->getPrimaryKeyName(), $id, true));
    }
}
