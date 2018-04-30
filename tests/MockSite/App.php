<?php
namespace Divergence\Tests\MockSite;

use Divergence\IO\Database\MySQL as DB;
use Divergence\IO\Database\SQL as SQL;
use Divergence\Tests\MockSite\Models\Tag;

class App extends \Divergence\App
{
    public static function isDatabaseTestingEnabled() {
        try {
            return is_a(DB::getConnection(),\PDO::class);
        }
        catch(\Exception $e) {
            return false;
        }
    }

    public static function setUp() {
        if(static::isDatabaseTestingEnabled()) {
            static::clean();

            $Tags = [
                ['Tag'=>'Linux','Slug' => 'linux'],
                ['Tag'=>'OSX','Slug' => 'osx'],
                ['Tag'=>'PHPUnit','Slug' => 'phpunit'],
                ['Tag'=>'Unit Testing','Slug' => 'unit_testing']
            ];
            
            foreach($Tags as &$Tag) {
                $Tag = Tag::create($Tag);
                $Tag->save();
            }
        }
    }

    public static function clean() {
        $tables = DB::allRecords('Show tables;');
        foreach($tables as $data) {
            foreach($data as $table) {
                DB::nonQuery("DROP TABLE `{$table}`");
            }
        }
    }

    public static function init($Path)
    {
        return parent::init($Path);
    }
}
