<?php
namespace Divergence\Tests\MockSite\Controllers;

use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Models\ActiveRecord;

use Divergence\Tests\MockSite\Controllers\CanaryRequestHandler;

class SecureCanaryRequestHandler extends CanaryRequestHandler
{

    public static function checkBrowseAccess($arguments)
    {
        return false;
    }

    public static function checkReadAccess(ActiveRecord $Record)
    {
        return false;
    }
    
    public static function checkWriteAccess(ActiveRecord $Record)
    {
        return false;
    }
    
    public static function checkAPIAccess()
    {
        return true;
    }
}
