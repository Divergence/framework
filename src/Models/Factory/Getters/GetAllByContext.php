<?php

namespace Divergence\Models\Factory\Getters;

use Exception;

class GetAllByContext extends ModelGetter
{
    public function getAllByContext($contextClass, $contextID, $options = [])
    {
        if (!$this->fieldExists('ContextClass')) {
            throw new Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = $this->prepareOptions($options, [
            'conditions' => [],
        ]);

        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;

        return $this->instantiateRecords($this->factory->getAllRecordsByWhere($options['conditions'], $options));
    }
}
