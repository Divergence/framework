<?php

namespace Divergence\Models\Events;

use Divergence\Models\ActiveRecord;

class AfterSave extends AbstractHandler
{
    public static function handle(ActiveRecord $model): void
    {
        foreach (static::getStaticProperty(get_class($model), '_classAfterSave') ?? [] as $afterSave) {
            if (is_callable($afterSave)) {
                $afterSave($model);
            }
        }
    }
}
