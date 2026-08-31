<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite\Collections;

use Divergence\Models\Collections\RecordCollection;
use Divergence\Tests\MockSite\Models\Canary;

class CanaryCollection extends RecordCollection
{
    public function __construct(array $records = [])
    {
        parent::__construct($records, array_keys(Canary::getClassFields()), Canary::class);
    }
}
