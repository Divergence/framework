<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Models\RecordValidator;

class TestableRecordValidator extends RecordValidator
{
    public function getProtected($field)
    {
        return $this->$field;
    }
}
