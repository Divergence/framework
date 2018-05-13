<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Models\Relations;

class relationalTag extends Tag
{
    use Relations;
    
    public static $relationships = [
        'ContextChildren' => [
            'type' => 'context-children',
            'class' => Canary::class,
            'contextClass' => Tag::class,
        ],
    ];
}