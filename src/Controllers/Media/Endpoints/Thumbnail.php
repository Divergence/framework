<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\Media\Media;
use Divergence\Responders\EmptyBuilder;
use Divergence\Responders\MediaBuilder;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Thumbnail extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$Media] = array_pad($arguments, 1, null);

        if (!$Media) {
            if (!$mediaID = $this->handler->shiftPath()) {
                return $this->handler->throwNotFoundError();
            } elseif (!$Media = Media::getByID($mediaID)) {
                return $this->handler->throwNotFoundError();
            }
        }

        $_server = $this->handler->getRequest()->getServerParams();

        if (!empty($_server['HTTP_IF_NONE_MATCH']) || !empty($_server['HTTP_IF_MODIFIED_SINCE'])) {
            $this->handler->responseBuilder = EmptyBuilder::class;
            $response = $this->handler->respondEmpty($Media->ID);
            $response->withDefaults(304);

            return $response;
        }

        if (preg_match('/^(\d+)x(\d+)(x([0-9A-F]{6})?)?$/i', $this->handler->peekPath(), $matches)) {
            $this->handler->shiftPath();
            $maxWidth = $matches[1];
            $maxHeight = $matches[2];
            $fillColor = !empty($matches[4]) ? $matches[4] : false;
        } else {
            $maxWidth = $this->handler->defaultThumbnailWidth;
            $maxHeight = $this->handler->defaultThumbnailHeight;
            $fillColor = false;
        }

        $cropped = false;
        if ($this->handler->peekPath() == 'cropped') {
            $this->handler->shiftPath();
            $cropped = true;
        }

        try {
            $thumbPath = $Media->getThumbnail($maxWidth, $maxHeight, $fillColor, $cropped);
            $this->handler->responseBuilder = MediaBuilder::class;

            return $this->handler->respondWithThumbnail($Media, "$maxWidth-$maxHeight-$fillColor-$cropped", $thumbPath);
        } catch (Exception $e) {
            return $this->handler->throwNotFoundError();
        }
    }
}
