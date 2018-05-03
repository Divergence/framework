<?php
namespace Divergence\Tests\IO\Database;

use Divergence\IO\Database\SQL;
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

        $Connection = DB::getConnection();
        $z = function ($x) use ($Connection) {
            $x = $Connection->quote($x);
            return substr($x, 1, strlen($x)-2);
        };

        $littleBobbyTables = 'Robert\'); DROP TABLE Students;--';
        $safeLittleBobbyTables = $z($littleBobbyTables);
        
        $arrayOfBobbies = [
            'lorum ipsum',
            $littleBobbyTables,
            '; DROP tests ',
        ];
        $safeArrayOfBobbies = [];

        foreach ($arrayOfBobbies as $oneBob) {
            $safeArrayOfBobbies[] = $z($oneBob);
        }
        
        $this->assertEquals($safeLittleBobbyTables, SQL::escape($littleBobbyTables));
        $this->assertEquals($safeArrayOfBobbies, SQL::escape($arrayOfBobbies));
    }

    public function testGetCreateTable()
    {
        $Expected[Tag::class] = 'ae3e735ba26bdd70332877d0458a5ff98a6580dc';
        $Expected[Canary::class] = '6aadc6f7938c72d1bf78c4eb8a20ba2966b773fd';

        foreach ($Expected as $Class=>$Hash) {
            $this->assertEquals($Hash, sha1(SQL::getCreateTable($Class)));
        }
    }

    public function testGetCreateTableVersioned()
    {
        $Expected[Canary::class] = 'f6f9210d08baae57bad192b7475552071fbc9d82';
        foreach ($Expected as $Class=>$Hash) {
            $this->assertEquals($Hash, sha1(SQL::getCreateTable($Class, true)));
        }
    }
}
