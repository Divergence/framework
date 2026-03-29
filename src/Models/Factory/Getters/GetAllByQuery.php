<?php

namespace Divergence\Models\Factory\Getters;

class GetAllByQuery extends ModelGetter
{
    public function getAllByQuery($query, $params = [])
    {
        return $this->instantiateRecords($this->getStorage()->allRecords($query, $params, $this->getHandleExceptionCallback()));
    }
}
