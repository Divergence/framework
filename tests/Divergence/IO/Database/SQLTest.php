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
        $Expected[Tag::class] = 'ae3e735ba26bdd70332877d0458a5ff98a6580dc';
        $Expected[Canary::class] = '9aca8005cf7bf72f3873de36c14dbf121c4bca35';

        foreach ($Expected as $Class=>$Hash) {
            $this->assertEquals($Hash, sha1(SQL::getCreateTable($Class)));
        }
    }

    public function testGetCreateTableVersioned()
    {
        $Expected[Canary::class] = '492078c2af3848f4b4d4448b8bdf1086310e2bd5';
        foreach ($Expected as $Class=>$Hash) {
            $this->assertEquals($Hash, sha1(SQL::getCreateTable($Class, true)));
        }
    }
}
