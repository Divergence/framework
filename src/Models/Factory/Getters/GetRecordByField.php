<?php

namespace Divergence\Models\Factory\Getters;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetRecordByField extends ModelGetter
{
    public function getRecordByField($field, $value, $cacheIndex = false)
    {
        return $this->factory->getRecordByWhere([$this->getColumnName($field) => $this->getStorage()->escape($value)], $cacheIndex);
    }
}
