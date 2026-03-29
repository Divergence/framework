<?php

namespace Divergence\Models\Factory\Getters;

class GetTableByQuery extends ModelGetter
{
    public function getTableByQuery($keyField, $query, $params = [])
    {
        return $this->instantiateRecords($this->getStorage()->table($keyField, $query, $params, $this->getHandleExceptionCallback()));
    }
}
