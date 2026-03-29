<?php

namespace Divergence\Models\Factory\Getters;

class GetRecordByWhere extends ModelGetter
{
    public function getRecordByWhere($conditions, $options = [])
    {
        if (!is_array($conditions)) {
            $conditions = [$conditions];
        }

        $options = $this->prepareOptions($options, [
            'order' => false,
        ]);

        $conditions = $this->mapConditions($conditions);
        $order = $options['order'] ? $this->mapFieldOrder($options['order']) : [];

        return $this->getStorage()->oneRecord(
            $this->newSelect()
                ->setTable($this->getTableName())
                ->where(join(') AND (', $conditions))
                ->order($order ? join(',', $order) : '')
                ->limit('1'),
            null,
            $this->getHandleExceptionCallback()
        );
    }
}
