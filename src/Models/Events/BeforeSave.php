<?php

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
