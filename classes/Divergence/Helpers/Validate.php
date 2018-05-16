<?php
namespace Divergence\Helpers;

class Validate
{
    public static function string($string, array $options = [])
    {
        $options = array_merge([
            'minlength' => 1,
            'maxlength' => false,
        ], $options);

        return !empty($string) && is_string($string)
            && (strlen($string) >= $options['minlength'])
            && (($options['maxlength'] == false) || (strlen($string) <= $options['maxlength']));
    }

    public static function number($number, array $options = [])
    {
        $options = array_merge([
            'min' => false,
            'max' => false,
        ], $options);

        return is_numeric($number)
            && (($options['min'] === false) || ($number >= $options['min']))
            && (($options['max'] === false) || ($number <= $options['max']));
    }

    public static function email($email, array $options = [])
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        } else {
            return false;
        }
    }
}
