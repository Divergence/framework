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

class BeforeSave extends AbstractHandler
{
    public static function handle(ActiveRecord $model): void
    {
        foreach (static::getStaticProperty(get_class($model), '_classBeforeSave') ?? [] as $beforeSave) {
            if (is_callable($beforeSave)) {
                $beforeSave($model);
            }
        }
    }
}
