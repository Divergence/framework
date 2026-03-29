<?php

namespace Divergence\Models\Factory\Getters;

use Divergence\Models\ActiveRecord;

class GetByContextObject extends ModelGetter
{
    public function getByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->factory->getByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }
}
