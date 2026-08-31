<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\IO\Database;

use Divergence\IO\Database\Writer\MySQL as SQL;
use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Tests\MockSite\App;
use Divergence\IO\Database\MySQL as DB;

use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;

class SQLTest extends TestCase
{
    public function testEscape()
    {
        TestUtils::requireDB($this);

        $littleBobbyTables = 'Robert\'); DROP TABLE Students;--';
        $arrayOfBobbies = [
            'lorum ipsum',
            $littleBobbyTables,
            '; DROP tests ',
        ];

        $this->assertEquals("Robert\\'); DROP TABLE Students;--", SQL::escape($littleBobbyTables));
        $this->assertEquals([
            'lorum ipsum',
            "Robert\\'); DROP TABLE Students;--",
            '; DROP tests ',
        ], SQL::escape($arrayOfBobbies));
    }

    public function testGetCreateTable()
    {
        $Expected[Tag::class] = '2a6459dc1f43846be657a77347c221e76a66f88a';
        $Expected[Canary::class] = 'c80a4195924a505ef974aa43c6e91d7c688fc460';

        foreach ($Expected as $Class=>$Hash) {
            $this->assertEquals($Hash, sha1(SQL::getCreateTable($Class)));
        }
    }

    public function testGetCreateTableVersioned()
    {
        $Expected[Canary::class] = '950d3081e5c841834cda565bc1e70e4e3420a65a';
        foreach ($Expected as $Class=>$Hash) {
            $this->assertEquals($Hash, sha1(SQL::getCreateTable($Class, true)));
        }
    }
}
