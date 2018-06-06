<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Helpers;

/**
 * Util
 *
 * @package Divergence
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 */
class Util
{
    /**
     * Prepares options.
     *
     * @param string|array $value Option. If provided a string will be assumed to be json and it will attempt to json_decode it and merge it with defaults. Or provide the array yourself.
     * @param array $defaults Defaults for the options array
     * @return array Merged array from $defaults and $value
     */
    public static function prepareOptions($value, $defaults = [])
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? array_merge($defaults, $value) : $defaults;
    }
}
