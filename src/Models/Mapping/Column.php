<?php

namespace Divergence\Models\Mapping;

use Attribute;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY","ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Column implements MappingAttribute
{
    /**
     * @var string|null
     * @readonly
     */
    public $columnName;

    /**
     * @var mixed
     * @readonly
     */
    public $type;

    /**
     * @var int|null
     * @readonly
     */
    public $length;

    /**
     * The precision for a decimal (exact numeric) column (Applies only for decimal column).
     *
     * @var int|null
     * @readonly
     */
    public $precision = 0;

    /**
     * The scale for a decimal (exact numeric) column (Applies only for decimal column).
     *
     * @var int|null
     * @readonly
     */
    public $scale = 0;

    /**
     * @var bool
     * @readonly
     */
    public $unique = false;

    /**
     * @var bool
     * @readonly
     */
    public $notnull = true;

    /**
     * @var bool
     * @readonly
     */
    public $autoincrement;

    /**
     * @var bool
     * @readonly
     */
    public $unsigned;

    /**
     * @var bool
     * @readonly
     */
    public $primary;

    /**
     * @var bool
     * @readonly
     */
    public $insertable = true;

    /**
     * @var bool
     * @readonly
     */
    public $updatable = true;

    /**
     * @var string|null
     * @readonly
     */
    public $delimiter = null;

    /**
     * @var array<string,mixed>
     * @readonly
     */
    public $values = [];

    /**
     * @var array<string,mixed>
     * @readonly
     */
    public $options = [];

    /**
     * @var string|null
     * @readonly
     */
    public $columnDefinition;

    /**
     * @var string|null
     * @readonly
     * @psalm-var 'NEVER'|'INSERT'|'ALWAYS'|null
     * @Enum({"NEVER", "INSERT", "ALWAYS"})
     */
    public $generated;

    /**
     * @param array<string,mixed>           $options
     * @psalm-param 'NEVER'|'INSERT'|'ALWAYS'|null $generated
     */
    public function __construct(
        ?string $columnName = null,
        ?string $type = null,
        ?int $length = null,
        ?int $precision = null,
        ?int $scale = null,
        bool $unique = false,
        bool $notnull = true,
        bool $autoincrement = false,
        bool $unsigned = null,
        bool $primary = false,
        bool $insertable = true,
        bool $updatable = true,
        string $delimiter = null,
        array $values = [],
        array $options = [],
        ?string $columnDefinition = null,
        ?string $generated = null
    ) {
        $this->columnName       = $columnName;
        $this->type             = $type;
        $this->length           = $length;
        $this->precision        = $precision;
        $this->scale            = $scale;
        $this->unique           = $unique;
        $this->notnull          = $notnull;
        $this->autoincrement    = $autoincrement;
        $this->unsigned         = $unsigned;
        $this->primary          = $primary;
        $this->insertable       = $insertable;
        $this->updatable        = $updatable;
        $this->delimiter        = $delimiter;
        $this->values           = $values;
        $this->options          = $options;
        $this->columnDefinition = $columnDefinition;
        $this->generated        = $generated;
    }
}
