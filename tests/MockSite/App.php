<?php
namespace Divergence\Tests\MockSite;

use Divergence\IO\Database\SQL as SQL;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;

class App extends \Divergence\App
{
    public static function setUp()
    {
        if (static::isDatabaseTestingEnabled()) {
            static::clean();

            $Tags = [
                ['Tag'=>'Linux','Slug' => 'linux'],
                ['Tag'=>'OSX','Slug' => 'osx'],
                ['Tag'=>'PHPUnit','Slug' => 'phpunit'],
                ['Tag'=>'Unit Testing','Slug' => 'unit_testing'],
                ['Tag'=>'Refactoring','Slug' => 'refactoring'],
                ['Tag'=>'Encryption','Slug' => 'encryption'],
            ];
            
            foreach ($Tags as &$Tag) {
                $Tag = Tag::create($Tag);
                $Tag->save();
            }

            fwrite(STDOUT, 'Summoning Canaries'."\n");

            $Canaries = [];
            while (count($Canaries) < 10) {
                $Canary = Canary::create(Canary::avis());
                
                $Canary->save();
                array_push($Canaries, $Canary);
            }
        }
    }
    public static function isDatabaseTestingEnabled()
    {
        try {
            return is_a(DB::getConnection(), \PDO::class);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function clean()
    {
        $tables = DB::allRecords('Show tables;');
        foreach ($tables as $data) {
            foreach ($data as $table) {
                DB::nonQuery("DROP TABLE `{$table}`");
            }
        }
    }

    public static function init($Path)
    {
        return parent::init($Path);
    }
}
