<?php

namespace Divergence\Models\Factory\Getters;

class GetRecordByField extends ModelGetter
{
    public function getRecordByField($field, $value, $cacheIndex = false)
    {
        return $this->factory->getRecordByWhere([$this->getColumnName($field) => $this->getStorage()->escape($value)], $cacheIndex);
    }
}
