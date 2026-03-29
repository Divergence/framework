<?php

namespace Divergence\Models\Factory\Getters;

class GetAllRecords extends ModelGetter
{
    public function getAllRecords($options = [])
    {
        $options = $this->prepareOptions($options, [
            'indexField' => false,
            'order' => false,
            'limit' => false,
            'calcFoundRows' => false,
            'offset' => 0,
        ]);

        $select = $this->newSelect()->setTable($this->getTableName())->calcFoundRows();

        if ($options['order']) {
            $select->order(join(',', $this->mapFieldOrder($options['order'])));
        }

        if ($options['limit']) {
            $select->limit(sprintf('%u,%u', $options['offset'], $options['limit']));
        }

        if ($options['indexField']) {
            return $this->getStorage()->table($this->getColumnName($options['indexField']), $select, null, null, $this->getHandleExceptionCallback());
        }

        return $this->getStorage()->allRecords($select, null, $this->getHandleExceptionCallback());
    }
}
