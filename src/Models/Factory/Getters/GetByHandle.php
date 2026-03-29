<?php

namespace Divergence\Models\Factory\Getters;

class GetByHandle extends ModelGetter
{
    public function getByHandle($handle)
    {
        $handleField = $this->getHandleFieldName();

        if ($this->fieldExists($handleField)) {
            if ($Record = $this->factory->getByField($handleField, $handle)) {
                return $Record;
            }
        }

        if (!is_int($handle) && !(is_string($handle) && ctype_digit($handle))) {
            return null;
        }

        return $this->factory->getByID($handle);
    }
}
