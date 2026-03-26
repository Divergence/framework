<?php

namespace Divergence\Models\Events;

use Divergence\Models\Factory\ModelMetadata;

class Delete extends AbstractHandler
{
    public static function handle(string $className, $id): bool
    {
        $storage = static::getStorage();
        $metadata = ModelMetadata::get($className);
        $primaryKeyColumn = $metadata->getColumnName($metadata->getPrimaryKey());
        $where = $metadata->hasIntegerPrimaryKey()
            ? sprintf('`%s` = %u', $primaryKeyColumn, intval($id))
            : sprintf('`%s` = %s', $primaryKeyColumn, $storage::quote($id));

        $storage->nonQuery(
            sprintf('DELETE FROM `%s` WHERE %s', $metadata->getTableName(), $where),
            null,
            $metadata->getHandleExceptionCallback()
        );
        return $storage->affectedRows() > 0;
    }
}
