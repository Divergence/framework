<?php

namespace Divergence\Models\Events;

use Divergence\IO\Database\Query\Insert;
use Divergence\IO\Database\Query\Update;
use Divergence\Models\ActiveRecord;
use Divergence\Models\Factory\ModelMetadata;
use Exception;

class Save extends AbstractHandler
{
    public static function handle(ActiveRecord $model, $deep = true): void
    {
        $className = get_class($model);
        $metadata = ModelMetadata::get($className);
        $storage = static::getStorage();

        $model->beforeSave();

        if ($metadata->isVersioned()) {
            $model->beforeVersionedSave();
        }

        if ($metadata->hasCreatedField() && (!$model->Created || ($model->Created == 'CURRENT_TIMESTAMP'))) {
            $model->primeFieldForSave('Created', time());
        }

        if (!$model->validate($deep)) {
            throw new Exception('Cannot save invalid record');
        }

        $model->clearCaches();

        if ($model->isDirty) {
            $set = $model->preparePersistedSet($metadata->getPersistedFieldConfigs());

            $model->cachePreparedPersistedSet($set);

            if ($model->isPhantom) {
                $storage->nonQuery((new Insert())->setTable($className::$tableName)->set($set), null, [$className, 'handleException']);

                $insertID = $storage->insertID();

                $model->finalizeInsert($insertID, $metadata->hasIntegerPrimaryKey());

                $set[] = $metadata->hasIntegerPrimaryKey()
                    ? sprintf('`%s` = %u', $metadata->getColumnName($metadata->getPrimaryKey()), intval($insertID))
                    : sprintf('`%s` = %s', $metadata->getColumnName($metadata->getPrimaryKey()), $storage::quote($insertID));

                $model->cachePreparedPersistedSet($set);
            } elseif (count($set)) {
                $storage->nonQuery(
                    (new Update())->setTable($className::$tableName)->set($set)->where(
                        sprintf('`%s` = %u', $className::getColumnName($model->getPrimaryKey()), (string) $model->getPrimaryKeyValue())
                    ),
                    null,
                    [$className, 'handleException']
                );

                $model->finalizeUpdate();
            }

            $model->finalizeSave();
            if ($metadata->isVersioned()) {
                $model->afterVersionedSave();
            }
        }

        $model->afterSave();

        $model->clearPreparedPersistedSet();
    }
}
