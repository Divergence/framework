<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\MockSite\Models\Forum;

use Divergence\Models\Relations;
use Divergence\Models\Versioning;

use Divergence\Models\Mapping\Column;

use Divergence\Models\Mapping\Relation;
use Divergence\Tests\MockSite\Models\Tag;

class TagPost extends \Divergence\Models\Model
{
    use Versioning;
    use Relations;

    // ActiveRecord configuration
    public static $tableName = 'forum_tag_post';

    // versioning
    public static $historyTable = 'forum_tag_post_history';
    public static $createRevisionOnDestroy = true;
    public static $createRevisionOnSave = true;

    #[Column(type: "integer", required:true, notnull: true)]
    private int $TagID;

    #[Column(type: "integer", required:true, notnull: true)]
    private int $PostID;

    public static $indexes = [
        'TagPost' => [
            'fields' => [
                'TagID',
                'PostID',
            ],
            'unique' => true,
        ],
    ];

    #[Relation(
        type:'one-one',
        class:Tag::class,
        local: 'ThreadID',
        foreign: 'ID',
    )]
    private ?Tag $Tag;

    #[Relation(
        type:'one-one',
        class:Post::class,
        local: 'PostID',
        foreign: 'ID',
    )]
    private ?Post $Post;
}
