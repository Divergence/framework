<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Tests\Models\Testables\fakeCanary;
use Divergence\Models\Versioning;
use Divergence\Models\Relations;

class relationalCanary extends fakeCanary
{
    use Versioning,Relations;

    public static $relationships = [
        'ContextParent' => [
            'type' => 'context-parent'
        ],
    ];
}