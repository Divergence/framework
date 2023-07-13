<?php

namespace Divergence\Models\Mapping;

use Attribute;

/**
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY","ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Relation implements MappingAttribute
{
    /**
     * @var string|null
     * @readonly
     */
    public $type;

    /**
     * @var string|null
     * @readonly
     */
    public $class;

    /**
     * @var mixed
     * @readonly
     */
    public $linkClass;

    /**
     * @var string|null
     * @readonly
     */
    public $linkLocal;

    /**
     * @var string|null
     * @readonly
     */
    public $linkForeign;

    /**
     * @var string|null
     * @readonly
     */
    public $local;

    /**
     * @var string|null
     * @readonly
     */
    public $foreign;

    /**
     * @var string|null
     * @readonly
     */
    public $indexField;

    /**
     * @var array|string|null
     * @readonly
     */
    public $conditions;

    /**
     * @var array|string|null
     * @readonly
     */
    public $order;

    public function __construct(
        ?string $type = null,
        ?string $class = null,
        ?string $linkClass = null,
        ?string $linkLocal = null,
        ?string $linkForeign = null,
        ?string $local = null,
        ?string $foreign = null,
        ?string $indexField = null,
        $conditions = null,
        $order = null,
    ) {
        $this->type = $type;
        $this->class = $class;
        $this->linkClass = $linkClass;
        $this->linkLocal = $linkLocal;
        $this->linkForeign = $linkForeign;
        $this->local = $local;
        $this->foreign = $foreign;
        $this->indexField = $indexField;
        $this->conditions = $conditions;
        $this->order = $order;
    }
}
