<?php
namespace Divergence\Data\Collections;

use Divergence\Data\KeyToHashInt;
use Divergence\Models\ActiveRecord;

class RecordKey
{
    public static function get($record)
    {
        // for our models we can rely on the primary key once it's been set
        if ($record instanceof ActiveRecord) {
            $primaryKey = $record->getPrimaryKeyValue();

            if ($primaryKey !== null) {
                // if we get a hash we return right away
                return KeyToHashInt::hashForKeys([$primaryKey]);
            } 
        }

        // this is phantoms and all non ORM objects that are indexed
        // after save phantoms will run ->remove() then ->add() on
        // themselves in the collection indexes

        // detect other ORMs here for indexing support
        return spl_object_id($record);
    }
}
