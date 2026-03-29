<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Psr\Http\Message\ResponseInterface;

class Delete extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        $GLOBALS['Session']->requireAccountLevel('Staff');

        $mediaIds = [];

        if ($mediaID = $this->handler->peekPath()) {
            $mediaIds = [$mediaID];
        } elseif (!empty($_REQUEST['mediaID'])) {
            $mediaIds = [$_REQUEST['mediaID']];
        } elseif (isset($_REQUEST['media']) && is_array($_REQUEST['media'])) {
            $mediaIds = $_REQUEST['media'];
        }

        $deleted = [];

        foreach ($mediaIds as $mediaID) {
            if (!is_numeric($mediaID)) {
                continue;
            }

            $Media = \Divergence\Models\Media\Media::getByID($mediaID);

            if (!$Media) {
                return $this->handler->throwNotFoundError();
            }

            if ($Media->destroy()) {
                $deleted[] = $Media;
            }
        }

        return $this->handler->respond('mediaDeleted', [
            'success' => true,
            'data' => $deleted,
        ]);
    }
}
