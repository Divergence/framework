<?php
namespace Divergence\Helpers;

class Util
{
    public static function prepareOptions($value, $defaults = [])
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        
        return is_array($value) ? array_merge($defaults, $value) : $defaults;
    }
}
