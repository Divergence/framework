<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\Media\Media;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Caption extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$mediaId] = $arguments;

        $GLOBALS['Session']->requireAccountLevel('Staff');

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

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $Media->Caption = $_REQUEST['Caption'];
            $Media->save();

            return $this->handler->respond('mediaCaptioned', [
                'success' => true,
                'data' => $Media,
            ]);
        }

        return $this->handler->respond('mediaCaption', [
            'data' => $Media,
        ]);
    }
}
