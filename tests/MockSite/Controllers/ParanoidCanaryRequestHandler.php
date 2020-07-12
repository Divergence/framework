<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests\MockSite\Controllers;

use Divergence\Models\ActiveRecord;
use Divergence\Tests\MockSite\Models\Canary;

use Divergence\Tests\MockSite\Controllers\CanaryRequestHandler;

class ParanoidCanaryRequestHandler extends SecureCanaryRequestHandler
{
    public function checkBrowseAccess($arguments)
    {
        return false;
    }

    public function checkReadAccess(ActiveRecord $Record)
    {
        return false;
    }

    public function checkWriteAccess(ActiveRecord $Record)
    {
        return false;
    }

    public function checkAPIAccess()
    {
        return false;
    }
}
