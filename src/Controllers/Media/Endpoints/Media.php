<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Responders\EmptyBuilder;
use Divergence\Responders\JsonBuilder;
use Divergence\Responders\MediaBuilder;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Media extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$mediaID] = $arguments;

        if (empty($mediaID)) {
            return $this->handler->throwNotFoundError();
        }

        try {
            $Media = \Divergence\Models\Media\Media::getById($mediaID);
        } catch (Exception $e) {
            return $this->handler->throwUnauthorizedError();
        }

        if (!$Media) {
            return $this->handler->throwNotFoundError();
        }

        if (!$this->handler->checkReadAccess($Media)) {
            return $this->handler->throwNotFoundError();
        }

        $_server = $this->handler->getRequest()->getServerParams();

        if (isset($_server['HTTP_ACCEPT']) && $_server['HTTP_ACCEPT'] == 'application/json') {
            $this->handler->responseBuilder = JsonBuilder::class;
        }

        if ($this->handler->responseBuilder == JsonBuilder::class) {
            return $this->handler->respond('media', [
                'success' => true,
                'data' => $Media,
            ]);
        }

        if ($variant = $this->handler->shiftPath()) {
            if (!$Media->isVariantAvailable($variant)) {
                return $this->handler->throwNotFoundError();
            }
        } else {
            $variant = 'original';
        }

        $this->handler->responseBuilder = MediaBuilder::class;
        set_time_limit(0);
        $filePath = $Media->getFilesystemPath($variant);

        if (!empty($_server['HTTP_IF_NONE_MATCH']) || !empty($_server['HTTP_IF_MODIFIED_SINCE'])) {
            $this->handler->responseBuilder = EmptyBuilder::class;
            $response = $this->handler->respondEmpty($filePath);
            $response->withDefaults(304);

            return $response;
        }

        return $this->handler->respondWithMedia($Media, $variant, $filePath);
    }
}
