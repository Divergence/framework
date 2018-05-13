<?php
namespace Divergence\Tests\Models\Testables;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;
use Divergence\Tests\Models\Testables\fakeCanary;

class relationalCanary extends fakeCanary
{
    use Versioning,Relations;

    public static $relationships = [
        'ContextParent' => [
            'type' => 'context-parent',
        ],
    ];
}
