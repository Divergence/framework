<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\Media\Media;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Info extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$mediaID] = $arguments;

        if (empty($mediaID) || !is_numeric($mediaID)) {
            return $this->handler->throwNotFoundError();
        }

        try {
            $Media = Media::getById($mediaID);
        } catch (Exception $e) {
            return $this->handler->throwUnauthorizedError();
        }

        if (!$Media) {
            return $this->handler->throwNotFoundError();
        }

        if (!$this->handler->checkReadAccess($Media)) {
            return $this->handler->throwUnauthorizedError();
        }

        return $this->handler->handleRecordRequest($Media);
    }
}
