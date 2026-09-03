<?php

namespace Divergence\Models\Factory\Getters;

use Divergence\Models\ActiveRecord;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
class GetByContextObject extends ModelGetter
{
    public function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->factory->getByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }
}
