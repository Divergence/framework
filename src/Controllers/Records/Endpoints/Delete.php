<?php

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Psr\Http\Message\ResponseInterface;

class Delete extends AbstractRecordsEndpoint
{
    protected RecordsRequestHandler $handler;

    public function __construct(RecordsRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$Record] = $arguments;
        $className = $this->handler::$recordClass;

        if (!$this->handler->checkWriteAccess($Record)) {
            return $this->handler->throwUnauthorizedError();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = $Record->data;
            $Record->destroy();
            $this->handler->onRecordDeletedHook($Record, $data);

            return $this->handler->respond($this->handler->getTemplateName($className::getSingularNoun()).'Deleted', [
                'success' => true,
                'data' => $Record,
            ]);
        }

        return $this->handler->respond('confirm', [
            'question' => 'Are you sure you want to delete this '.$className::getSingularNoun().'?',
            'data' => $Record,
        ]);
    }
}
