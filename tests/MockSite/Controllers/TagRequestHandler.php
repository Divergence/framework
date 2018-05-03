<?php
namespace Divergence\Tests\MockSite\Controllers;

use Divergence\Tests\MockSite\Models\Tag;

use Divergence\Controllers\RecordsRequestHandler;

class TagRequestHandler extends RecordsRequestHandler
{
    public static $recordClass = Tag::class;
}
