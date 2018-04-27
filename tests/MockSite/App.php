<?php
namespace Divergence\Tests\MockSite;

class App extends \Divergence\App
{
    public static function init($Path)
    {
        /*$Tag = Tag::create([
            'slug' => 'ssh',
            'tag' => 'SSH'
        ]);

        dump($Tag);*/

        return parent::init();
    }
}
