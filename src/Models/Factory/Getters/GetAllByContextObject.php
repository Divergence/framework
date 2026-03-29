<?php

namespace Divergence\Models\Factory\Getters;

use Divergence\Models\ActiveRecord;

class GetAllByContextObject extends ModelGetter
{
    public function getAllByContextObject(ActiveRecord $Record, $options = [])
    {
        return $this->factory->getAllByContext($Record::getRootClassName(), $Record->getPrimaryKeyValue(), $options);
    }
}
