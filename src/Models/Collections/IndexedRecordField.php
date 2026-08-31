<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Collections;

use Divergence\Data\Collections\IndexedField;
use Divergence\Data\Collections\RecordKey;

class IndexedRecordField extends IndexedField
{
    public function clearExistingIndexForValue($record)
    {
        $modelKey = RecordKey::get($record);

        if (isset($this->values[$modelKey])) {
            $cardinality = $this->values[$modelKey];
            unset($this->index[$cardinality][$modelKey]);
            unset($this->values[$modelKey]);
            $this->invalidateOrdering();
        }
    }

    public function set($record)
    {
        $cardinalityValue = $this->indexableValue($record->getValue($this->field));

        if (!$this->cardinality_exists($cardinalityValue)) {
            $this->bootstrapCardinality($cardinalityValue);
        }

        $this->clearExistingIndexForValue($record);

        $cardinality = $this->cardinalityKey($cardinalityValue);
        $modelKey = RecordKey::get($record);

        $this->index[$cardinality][$modelKey] = true;
        $this->values[$modelKey] = $cardinality;
        $this->invalidateOrdering();
    }

    public function indexableValue($value)
    {
        switch ($this->type) {
            case 'DateString':
            case 'timestamp':
                return strtotime($value) ?: $value;

            default:
                $type = gettype($value);
                if ($type === 'float' || $type === 'double') {
                    $value = (string) $value;
                }
                return $value;
        }
    }
}
