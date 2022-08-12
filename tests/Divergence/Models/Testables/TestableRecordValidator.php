<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Models\Testables;

use Divergence\Models\RecordValidator;

class TestableRecordValidator extends RecordValidator
{
    public function getProtected($field)
    {
        return $this->$field;
    }
}
