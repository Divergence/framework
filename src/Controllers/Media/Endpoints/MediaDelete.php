<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\Media\Media;
use Psr\Http\Message\ResponseInterface;

class MediaDelete extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        if (empty($_REQUEST['media']) || !is_array($_REQUEST['media'])) {
            return $this->handler->throwNotFoundError();
        }

        $mediaArray = [];
        foreach ($_REQUEST['media'] as $mediaId) {
            if (!is_numeric($mediaId)) {
                return $this->handler->throwNotFoundError();
            }

            if ($Media = Media::getById($mediaId)) {
                $mediaArray[$Media->ID] = $Media;

                if (!$this->handler->checkWriteAccess($Media)) {
                    return $this->handler->throwUnauthorizedError();
                }
            }
        }

        $deleted = [];
        foreach ($mediaArray as $mediaId => $Media) {
            if ($Media->delete()) {
                $deleted[] = $mediaId;
            }
        }

        return $this->handler->respond('mediaDeleted', [
            'success' => true,
            'deleted' => $deleted,
        ]);
    }
}
