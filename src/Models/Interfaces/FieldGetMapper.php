<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Interfaces;

/**
 * Field Get Mapper interface
 *
 * This is how each Model field type is processed.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
interface FieldGetMapper
{
    public static function getStringValue($value): ?string;
    public static function getBooleanValue($value): bool;
    public static function getDecimalValue($value): ?float;
    public static function getIntegerValue($value): ?int;
    public static function getDateValue($value);
    public static function getTimestampValue($value): ?int;
    public static function getSerializedValue($value);
    public static function getListValue($value, ?string $delimiter): array;
}
