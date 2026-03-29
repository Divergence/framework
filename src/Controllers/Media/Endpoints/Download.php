<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\Media\Media;
use Divergence\Responders\MediaBuilder;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Download extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$mediaId, $filename] = array_pad($arguments, 2, false);

        if (empty($mediaId) || !is_numeric($mediaId)) {
            return $this->handler->throwNotFoundError();
        }

        try {
            $Media = Media::getById($mediaId);
        } catch (Exception $e) {
            return $this->handler->throwUnauthorizedError();
        }

        if (!$Media) {
            return $this->handler->throwNotFoundError();
        }

        if (!$this->handler->checkReadAccess($Media)) {
            return $this->handler->throwUnauthorizedError();
        }

        $filePath = $Media->getFilesystemPath('original');
        $this->handler->responseBuilder = MediaBuilder::class;
        $response = $this->handler->respondWithMedia($Media, 'original', $filePath);

        return $response->withHeader('Content-Disposition', 'attachment; filename="'.($filename ? $filename : $filePath).'"');
    }
}
