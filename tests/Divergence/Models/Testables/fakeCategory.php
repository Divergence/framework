<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Tests\MockSite\Models\Forum\Category;
use Divergence\Models\Versioning;
use Divergence\Models\Relations;

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