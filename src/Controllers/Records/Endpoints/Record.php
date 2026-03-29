<?php

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Divergence\Models\ActiveRecord;
use Psr\Http\Message\ResponseInterface;

class Record extends AbstractRecordsEndpoint
{
    protected RecordsRequestHandler $handler;

    public function __construct(RecordsRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$Record, $action] = array_pad($arguments, 2, false);

        if (!$this->handler->checkReadAccess($Record)) {
            return $this->handler->throwUnauthorizedError();
        }

        switch ($action ?: $action = $this->handler->shiftPath()) {
            case '':
            case false:
                $className = $this->handler::$recordClass;

                return $this->handler->respond($this->handler->getTemplateName($className::getSingularNoun()), [
                    'success' => true,
                    'data' => $Record,
                ]);

            case 'edit':
                return $this->handler->handleEditRequest($Record);

            case 'delete':
                return $this->handler->handleDeleteRequest($Record);

            default:
                return $this->handler->onRecordRequestNotHandled($Record, $action);
        }
    }
}
