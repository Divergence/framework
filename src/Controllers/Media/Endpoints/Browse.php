<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\Tag;
use Psr\Http\Message\ResponseInterface;

class Browse extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$options, $conditions, $responseID, $responseData] = array_pad($arguments, 4, null);
        $conditions ??= [];
        $responseData ??= [];

        if (!empty($_REQUEST['tag'])) {
            if (!$Tag = Tag::getByHandle($_REQUEST['tag'])) {
                return $this->handler->throwNotFoundError();
            }

            $conditions[] = 'ID IN (SELECT ContextID FROM tag_items WHERE TagID = '.$Tag->ID.' AND ContextClass = "Product")';
        }

        if (!empty($_REQUEST['ContextClass'])) {
            $conditions['ContextClass'] = $_REQUEST['ContextClass'];
        }

        if (!empty($_REQUEST['ContextID']) && is_numeric($_REQUEST['ContextID'])) {
            $conditions['ContextID'] = $_REQUEST['ContextID'];
        }

        return $this->parentBrowse($options, $conditions, $responseID, $responseData);
    }

    protected function parentBrowse($options, $conditions, $responseID, $responseData): ResponseInterface
    {
        return $this->handler->__call('handleBrowseRequest', [$options, $conditions, $responseID, $responseData]);
    }
}
