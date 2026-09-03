<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetAllByQuery extends ModelGetter
{
    public function getAllByQuery($query, $params = [])
    {
        return $this->instantiateRecords($this->getStorage()->allRecords($query, $params, $this->getHandleExceptionCallback()));
    }
}
