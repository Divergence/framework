<?php

namespace Divergence\Models\Factory\Getters;

use Divergence\IO\Database\Connections;
use Divergence\IO\Database\PostgreSQL;

class GetAllRecordsByWhere extends ModelGetter
{
    public function getAllRecordsByWhere($conditions = [], $options = [])
    {
        $options = $this->prepareOptions($options, [
            'indexField' => false,
            'order' => false,
            'limit' => false,
            'offset' => 0,
            'calcFoundRows' => !empty($options['limit']),
            'extraColumns' => false,
            'having' => false,
        ]);

        if ($conditions) {
            if (is_string($conditions)) {
                $conditions = [$conditions];
            }

            $conditions = $this->mapConditions($conditions);
        }

        $tableAlias = $this->getSelectTableAlias();
        $select = $this->newSelect()->setTable($this->getTableName())->setTableAlias($tableAlias);

        if ($options['calcFoundRows']) {
            $select->calcFoundRows();
        }

        $expression = sprintf('`%s`.*', $tableAlias);
        $select->expression($expression.$this->buildExtraColumns($options['extraColumns']));

        $whereClause = $conditions ? join(') AND (', $conditions) : null;

        if ($conditions) {
            $select->where($whereClause);
        }

        if ($options['having']) {
            $havingClause = $this->buildHaving($options['having'], $options['extraColumns']);

            if (Connections::getConnectionType() === PostgreSQL::class) {
                $select->where($whereClause ? $whereClause . ' AND ' . trim($havingClause) : trim($havingClause));
            } else {
                $select->having($havingClause);
            }
        }

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
