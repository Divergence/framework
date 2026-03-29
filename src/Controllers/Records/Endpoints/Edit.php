<?php

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Divergence\Helpers\JSON;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Edit extends AbstractRecordsEndpoint
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

        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
            if ($this->handler->responseBuilder === \Divergence\Responders\JsonBuilder::class) {
                $_REQUEST = JSON::getRequestData();
                if (isset($_REQUEST['data']) && is_array($_REQUEST['data'])) {
                    $_REQUEST = $_REQUEST['data'];
                }
            }

            $_REQUEST = $_REQUEST ?: $_POST;

            $this->handler->applyRecordDelta($Record, $_REQUEST);
            $this->handler->onBeforeRecordValidatedHook($Record, $_REQUEST);

            if ($Record->validate()) {
                $this->handler->onBeforeRecordSavedHook($Record, $_REQUEST);

                try {
                    $Record->save();
                } catch (Exception $e) {
                    return $this->handler->respond('Error', [
                        'success' => false,
                        'failed' => [
                            'errors' => $e->getMessage(),
                        ],
                    ]);
                }

                $this->handler->onRecordSavedHook($Record, $_REQUEST);

                return $this->handler->respond($this->handler->getTemplateName($className::getSingularNoun()).'Saved', [
                    'success' => true,
                    'data' => $Record,
                ]);
            }
        }

        return $this->handler->respond($this->handler->getTemplateName($className::getSingularNoun()).'Edit', [
            'success' => false,
            'data' => $Record,
        ]);
    }
}
