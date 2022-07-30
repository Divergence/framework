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
    public function setStringValue($value): ?string;
    public function setBooleanValue($value): bool;
    public function setDecimalValue($value): ?float;
    public function setIntegerValue($value): ?int;
    public function setDateValue($value): ?string;
    public function setTimestampValue($value): ?string;
    public function setSerializedValue($value): string;
    public function setEnumValue(array $values, $value);
    public function setListValue($value, ?string $delimiter): array;
}
