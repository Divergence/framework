<?php

namespace Divergence\Models\Factory\Getters;

class GetByQuery extends ModelGetter
{
    public function getByQuery($query, $params = [])
    {
        return $this->instantiateRecord($this->getStorage()->oneRecord($query, $params, $this->getHandleExceptionCallback()));
    }
}
