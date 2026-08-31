<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Models\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class InMemoryIndexing implements MappingAttribute
{
    /** @var array<int, string> */
    public $indexes = [];

    public function __construct(array $indexes = [])
    {
        $this->indexes = $indexes;
    }
}
