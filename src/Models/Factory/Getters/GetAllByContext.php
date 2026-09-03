<?php

namespace Divergence\Models\Factory\Getters;

use Exception;

/**
 * @template TModel of \Divergence\Models\ActiveRecord
 * @extends ModelGetter<TModel>
 */
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
