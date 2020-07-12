<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Divergence\Tests;

use Divergence\Routing\Path;
use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    public function testPath()
    {
        $path = new Path('/two/three/four');

        $this->assertCount(3, $path->pathStack);
        $this->assertEquals(['two','three','four'], $path->requestPath);

        $this->assertEquals(4, $path->unshiftPath('one'));
        $this->assertEquals(['one','two','three','four'], $path->getPath());
        $this->assertEquals('one', $path->shiftPath());
        $this->assertEquals('two', $path->peekPath());
    }
}
