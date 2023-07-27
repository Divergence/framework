<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Mapping;

use Divergence\Models\Interfaces\FieldGetMapper;

/**
 * This is the default type mapping from PHP userspace to our Model field value
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class DefaultGetMapper implements FieldGetMapper
{
    public static function getStringValue($value): ?string
    {
        return $value;
    }


    public static function getBooleanValue($value): bool
    {
        return $value;
    }

    public static function getDecimalValue($value): ?float
    {
        return floatval($value);
    }

    public static function getIntegerValue($value): ?int
    {
        return intval($value);
    }

    public static function getDateValue($value)
    {
        return $value;
    }

    public static function getTimestampValue($value): ?int
    {
        if ($value && is_string($value) && $value != '0000-00-00 00:00:00') {
            return strtotime($value);
        } elseif (is_integer($value)) {
            return $value;
        }
        return null;
    }
    public static function getSerializedValue($value)
    {
        if (is_string($value)) {
            return unserialize($value);
        } else {
            return $value;
        }
    }

    public static function getListValue($value, ?string $delimiter = ','): array
    {
        if (is_array($value)) {
            return $value;
        }
        return array_filter(preg_split('/\s*'.$delimiter.'\s*/', $value));
    }
}
