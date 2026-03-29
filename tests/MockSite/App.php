<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite;

use Faker\Factory;
use Divergence\IO\Database\Connections;
use Divergence\Tests\MockSite\Models\Tag;
use Divergence\Tests\MockSite\Models\Canary;
use Divergence\Tests\MockSite\Models\Forum\Post;

use Divergence\Tests\MockSite\Models\Forum\Thread;
use Divergence\Tests\MockSite\Models\Forum\Category;

class App extends \Divergence\App
{
    public function setUp()
    {
        $faker = Factory::create();

        if ($this->isDatabaseTestingEnabled()) {
            $this->clean();

            $Tags = [
                ['Tag'=>'Linux','Slug' => 'linux'],
                ['Tag'=>'OSX','Slug' => 'osx'],
                ['Tag'=>'PHPUnit','Slug' => 'phpunit'],
                ['Tag'=>'Unit Testing','Slug' => 'unit_testing'],
                ['Tag'=>'Refactoring','Slug' => 'refactoring'],
                ['Tag'=>'Encryption','Slug' => 'encryption'],
                ['Tag'=>'Canaries','Slug'=> 'canaries'],
            ];

            foreach ($Tags as &$Tag) {
                $Tag = Tag::create($Tag);
                $Tag->save();
            }

            fwrite(STDOUT, 'Summoning Canaries'."\n");

            $Canaries = [];
            while (count($Canaries) < 10) {
                $Canary = Canary::create(Canary::mock());

                $Canary->save();
                array_push($Canaries, $Canary);
            }

            $data = Canary::mock();
            $data['Name'] = 'Versioned';
            $data['Handle'] = 'Versioned';
            $Canary = Canary::create($data, true);
            $Canary->Name = 'Version2';
            $Canary->Handle = 'Version2';
            $Canary->save();


            // forum data
            $Categories = [
                'General' => [
                    'Hey guys',
                    'found this stuff',
                    'meme dump',
                    'sticky! check this first!!!',
                ],
                'Entertainment' => [
                    'Top movies this year',
                    'leaked album',
                    'Checkout this stream',
                    'Ratings info',
                ],
                'Technology' => [
                    'New prices drop for next gen phones! Still no aux plug',
                    'Bluetooth headphones compared',
                    'ipv6 adoption rate picking up',
                ],
                'Support' => [
                    'Seeing a design bug in my browser',
                    'help!',
                    'can i haz human???',
                ],
            ];
            foreach ($Categories as $Category=>$Threads) {
                $Category = Category::create([
                    'Name' => $Category,
                ], true);

                foreach ($Threads as $Thread) {
                    $Thread = Thread::create([
                        'Title' => $Thread,
                        'CategoryID' => $Category->ID,
                    ], true);

                    $x = rand(0, 10);
                    $i = 0;
                    while ($i<$x) {
                        Post::create([
                            'Content' => $faker->text(rand(50, 400)),
                            'ThreadID' => $Thread->ID,
                        ], true);
                        $i++;
                    }
                }
            }
        }
    }
    public function isDatabaseTestingEnabled()
    {
        try {
            return is_a(Connections::getConnection(), \PDO::class);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function clean()
    {
        $pdo = Connections::getConnection();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver == 'sqlite') {
            $tables = \Divergence\IO\Database\StorageType::allValues('name', "SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` NOT LIKE 'sqlite_%'") ?? [];

            foreach ($tables as $table) {
                \Divergence\IO\Database\StorageType::nonQuery(sprintf('DROP TABLE `%s`', $table));
            }

            return;
        }

        if ($driver == 'pgsql') {
            $tables = \Divergence\IO\Database\StorageType::allValues('tablename', "SELECT tablename FROM pg_tables WHERE schemaname = current_schema()") ?? [];

            foreach ($tables as $table) {
                \Divergence\IO\Database\Connections::getConnection()->exec(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', str_replace('"', '""', $table)));
            }

            return;
        }

        $tables = \Divergence\IO\Database\StorageType::allRecords('Show tables;') ?? [];
        foreach ($tables as $data) {
            foreach ($data as $table) {
                \Divergence\IO\Database\StorageType::nonQuery("DROP TABLE `{$table}`");
            }
        }
    }

    public function tearDown(): void
    {
        if ($this->isDatabaseTestingEnabled()) {
            $this->clean();
        }

        $mediaPath = $this->ApplicationPath . '/media';
        if (is_dir($mediaPath)) {
            exec(sprintf('rm -rf %s', escapeshellarg($mediaPath)));
        }
    }
}
