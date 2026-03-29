<?php

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Divergence\Models\ActiveRecord;
use Psr\Http\Message\ResponseInterface;

class Create extends AbstractRecordsEndpoint
{
    protected RecordsRequestHandler $handler;

    public function __construct(RecordsRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$Record] = array_pad($arguments, 1, null);

        $this->handler->calledClass = get_called_class();

        if (!$Record) {
            $className = $this->handler::$recordClass;
            $defaultClass = $className::getDefaultClassName();
            $Record = new $defaultClass();
        }

        $this->handler->onRecordCreatedHook($Record, $_REQUEST);

        return $this->handler->handleEditRequest($Record);
    }
}
