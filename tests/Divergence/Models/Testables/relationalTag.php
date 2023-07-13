<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
