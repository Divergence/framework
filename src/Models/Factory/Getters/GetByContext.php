<?php

namespace Divergence\Models\Factory\Getters;

use Exception;

class GetByContext extends ModelGetter
{
    public function getByContext($contextClass, $contextID, $options = [])
    {
        if (!$this->fieldExists('ContextClass')) {
            throw new Exception('getByContext requires the field ContextClass to be defined');
        }

        $options = $this->prepareOptions($options, [
            'conditions' => [],
            'order' => false,
        ]);

        if (!$options['order']) {
            $options['order'] = [
                $this->getPrimaryKeyName() => 'ASC',
            ];
        }

        $options['conditions']['ContextClass'] = $contextClass;
        $options['conditions']['ContextID'] = $contextID;

        return $this->instantiateRecord($this->factory->getRecordByWhere($options['conditions'], $options));
    }
}
