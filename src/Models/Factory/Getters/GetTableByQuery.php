<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetTableByQuery extends ModelGetter
{
    public function getTableByQuery($keyField, $query, $params = [])
    {
        return $this->instantiateRecords($this->getStorage()->table($keyField, $query, $params, null, $this->getHandleExceptionCallback()));
    }
}
