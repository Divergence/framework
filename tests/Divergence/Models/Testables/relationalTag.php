<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Models\Relations;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;

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
