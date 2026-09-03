<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByQuery extends ModelGetter
{
    public function getByQuery($query, $params = [])
    {
        return $this->instantiateRecord($this->getStorage()->oneRecord($query, $params, $this->getHandleExceptionCallback()));
    }
}
