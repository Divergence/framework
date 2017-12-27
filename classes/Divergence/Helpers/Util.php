<?php
namespace Divergence\Helpers;

class Util {
    static public function prepareOptions($value, $defaults = array())
    {
        if(is_string($value))
        {
            $value = json_decode($value, true);
        }
        
        return is_array($value) ? array_merge($defaults, $value) : $defaults;
    }
}