<?php
namespace Divergence\Tests\IO\Database;

use Divergence\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use Divergence\Tests\MockSite\App;
use Divergence\IO\Database\MySQL as DB;
use Divergence\IO\Database\SQL;

use Divergence\Tests\MockSite\Models\Tag;

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
}