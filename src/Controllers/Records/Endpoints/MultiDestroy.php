<?php

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Exception;
use Psr\Http\Message\ResponseInterface;

class MultiDestroy extends AbstractRecordsEndpoint
{
    protected RecordsRequestHandler $handler;

    public function __construct(RecordsRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        $className = $this->handler::$recordClass;

        $this->handler->prepareResponseModeJSON(['POST', 'PUT', 'DELETE']);

        if (!empty($_REQUEST['data']) && $className::fieldExists(key($_REQUEST['data']))) {
            $_REQUEST['data'] = [$_REQUEST['data']];
        }

        if (empty($_REQUEST['data']) || !is_array($_REQUEST['data'])) {
            return $this->handler->respond('error', [
                'success' => false,
                'failed' => [
                    'errors' => 'Save expects "data" field as array of records.',
                ],
            ]);
        }

        $results = [];
        $failed = [];

        foreach ($_REQUEST['data'] as $datum) {
            try {
                $results[] = $this->processDatumDestroy($datum);
            } catch (Exception $e) {
                $failed[] = [
                    'record' => $datum,
                    'errors' => $e->getMessage(),
                ];
            }
        }

        return $this->handler->respond($this->handler->getTemplateName($className::getPluralNoun()).'Destroyed', [
            'success' => count($results) || !count($failed),
            'data' => $results,
            'failed' => $failed,
        ]);
    }

    protected function processDatumDestroy($datum)
    {
        $className = $this->handler::$recordClass;
        $PrimaryKey = $className::getPrimaryKey();

        if (is_numeric($datum)) {
            $recordID = $datum;
        } elseif (!empty($datum[$PrimaryKey]) && is_numeric($datum[$PrimaryKey])) {
            $recordID = $datum[$PrimaryKey];
        } else {
            throw new Exception($PrimaryKey.' missing');
        }

        if (!$Record = $className::getByField($PrimaryKey, $recordID)) {
            throw new Exception($PrimaryKey.' not found');
        }

        if (!$this->handler->checkWriteAccess($Record)) {
            throw new Exception('Write access denied');
        }

        if ($Record->destroy()) {
            return $Record;
        }

        throw new Exception('Destroy failed');
    }
}
