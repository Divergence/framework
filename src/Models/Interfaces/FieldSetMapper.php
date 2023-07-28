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
 * Field Set Mapper interface
 *
 * This is how each Model field type is processed.
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 */
interface FieldSetMapper
{
    public static function setStringValue($value): ?string;
    public static function setBooleanValue($value): bool;
    public static function setDecimalValue($value): ?float;
    public static function setIntegerValue($value): ?int;
    public static function setDateValue($value): ?string;
    public static function setTimestampValue($value): ?string;
    public static function setSerializedValue($value): string;
    public static function setEnumValue(array $values, $value);
    public static function setListValue($value, ?string $delimiter): array;
}
