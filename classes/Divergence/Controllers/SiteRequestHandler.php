<?php
namespace Divergence\Controllers;

class SiteRequestHandler extends RequestHandler
{
    public static function handleRequest()
    {
        phpinfo();
    }
}
