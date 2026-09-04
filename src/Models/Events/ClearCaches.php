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

use Divergence\Models\ActiveRecord;

class ClearCaches extends AbstractHandler
{
    public static function handle(ActiveRecord $model): void
    {
        $storage = static::getStorage();

        foreach ($model->getClassFields() as $field => $options) {
            if (!empty($options['unique']) || !empty($options['primary'])) {
                $key = sprintf('%s/%s', $model::$tableName, $field);
                $storage->clearCachedRecord($key);
            }
        }
    }
}
