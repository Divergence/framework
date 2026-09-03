<?php

namespace Divergence\Models\Factory\Getters;

use Divergence\Models\ActiveRecord;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetAllByContextObject extends ModelGetter
{
    public function getAllByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->factory->getAllByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }
}
