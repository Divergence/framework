<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite\Models;

use Divergence\Models\Mapping\Column;
use Divergence\Models\Model;

class FixtureItem extends Model
{
    public static $tableName = 'fixture_items';

    #[Column(type: 'integer')]
    private int $Team;

    #[Column(type: 'integer')]
    private int $Score;

    #[Column(type: 'string', unique: true)]
    private string $Name;
}
