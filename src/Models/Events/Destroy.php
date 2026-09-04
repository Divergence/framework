<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Events;

use Divergence\IO\Database\Query\Insert;
use Divergence\Models\ActiveRecord;

class Destroy extends AbstractHandler
{
    public static function handle(ActiveRecord $model): bool
    {
        $className = get_class($model);
        $storage = static::getStorage();

        if ($className::isVersioned()) {
            if ($className::$createRevisionOnDestroy) {
                if ($model->fieldExists('Created')) {
                    $model->Created = time();
                }

                $recordValues = static::callMethod($model, '_prepareRecordValues');
                $set = static::callStaticMethod($className, '_mapValuesToSet', [$recordValues]);

                $storage->nonQuery((new Insert())->setTable($className::getHistoryTable())->set($set), null, [$className, 'handleException']);
            }
        }

        $deleteHandler = $className::$deleteHandler;
        return $deleteHandler::handle($className, (string) $model->getPrimaryKeyValue());
    }
}
