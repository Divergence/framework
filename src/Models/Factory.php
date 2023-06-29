<?php

namespace Divergence\Factory;

use Exception;

class Factory
{
    protected string $className;

    public function __construct($className)
    {
        if (!is_subclass_of(ActiveRecord::class, $className)) {
            throw new Exception(sprintf('Provided class [%s] is not a subclass of ActiveRecord', $className));
        }

        $this->className = $className;
    }
}
