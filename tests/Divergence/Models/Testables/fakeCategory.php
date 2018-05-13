<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;
use Divergence\Tests\MockSite\Models\Forum\Category;

class fakeCategory extends Category
{
    use Versioning, Relations;

    public static $relationships = [];

    public static function setClassRelationships($x)
    {
        static::$_classRelationships = $x;
    }

    public static function getClassRelationships()
    {
        return static::$_classRelationships;
    }

    public static function initRelationship($relationship, $options)
    {
        return static::_initRelationship($relationship, $options);
    }
}
