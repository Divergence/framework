<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\SetMappers;

use Divergence\IO\Database\MySQL as DB;
use Divergence\Models\Interfaces\FieldSetMapper;

/**
 * This is the default type mapping from PHP userspace to our Model field value
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
class DefaultSetMapper implements FieldSetMapper
{
    public function setStringValue($value): ?string
    {
        return mb_convert_encoding($value??'', DB::$encoding, 'auto'); // normalize encoding to ASCII
    }

    public function setBooleanValue($value): bool
    {
        return (bool)$value;
    }

    public function setDecimalValue($value): ?float
    {
        return is_null($value) ? null : (float)preg_replace('/[^-\d.]/', '', $value);
    }

    public function setIntegerValue($value): ?int
    {
        return is_null($value) ? null : (int)preg_replace('/[^-\d]/', '', $value);
    }

    public function setTimestampValue($value): ?string
    {
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', $value);
        } elseif (is_string($value)) {
            // trim any extra stuff, or leave as-is if it doesn't fit the pattern
            if (preg_match('/^(\d{4})\D?(\d{2})\D?(\d{2})T?(\d{2})\D?(\d{2})\D?(\d{2})/', $value)) {
                return preg_replace('/^(\d{4})\D?(\d{2})\D?(\d{2})T?(\d{2})\D?(\d{2})\D?(\d{2})/', '$1-$2-$3 $4:$5:$6', $value);
            } else {
                return date('Y-m-d H:i:s', strtotime($value));
            }
        }
        return null;
    }

    /**
     * Parses a potential date value.
     * - If passed a number will assume it's a unix timestamp and convert to Y-m-d based on the provided timestamp.
     * - If passed a string will attempt to match m/d/y format.
     * - If not valid will then attempt  Y/m/d
     * - If passed an array will attempt to look for numeric values in the keys 'yyyy' for year, 'mm' for month, and 'dd' for day.
     * - If none of the above worked will attempt to use PHP's strtotime.
     * - Otherwise null.
     *
     * @param string|int|array $value Date or timestamp in various formats.
     * @return null|string Date formatted as Y-m-d
     */
    public function setDateValue($value): ?string
    {
        if (is_numeric($value)) {
            $value = date('Y-m-d', $value);
        } elseif (is_string($value)) {
            // check if m/d/y format
            if (preg_match('/^(\d{2})\D?(\d{2})\D?(\d{4}).*/', $value)) {
                $value = preg_replace('/^(\d{2})\D?(\d{2})\D?(\d{4}).*/', '$3-$1-$2', $value);
            }

            // trim time and any extra crap, or leave as-is if it doesn't fit the pattern
            $value = preg_replace('/^(\d{4})\D?(\d{2})\D?(\d{2}).*/', '$1-$2-$3', $value);
        } elseif (is_array($value) && count(array_filter($value))) {
            // collapse array date to string
            $value = sprintf(
                '%04u-%02u-%02u',
                is_numeric($value['yyyy']) ? $value['yyyy'] : 0,
                is_numeric($value['mm']) ? $value['mm'] : 0,
                is_numeric($value['dd']) ? $value['dd'] : 0
            );
        } else {
            if ($value = strtotime($value)) {
                $value = date('Y-m-d', $value) ?: null;
            } else {
                $value = null;
            }
        }
        return $value;
    }

    public function setSerializedValue($value): string
    {
        return serialize($value);
    }

    public function setEnumValue(array $values, $value)
    {
        return (in_array($value, $values) ? $value : null);
    }

    public function setListValue($value, ?string $delimiter): array
    {
        if (!is_array($value)) {
            $delim = empty($delimiter) ? ',' : $delimiter;
            $value = array_filter(preg_split('/\s*'.$delim.'\s*/', $value));
        }
        return $value;
    }
}
