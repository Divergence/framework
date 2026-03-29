<?php

namespace Divergence\Controllers\Records\Endpoints;

use Divergence\Controllers\Records\AbstractRecordsEndpoint;
use Divergence\Controllers\RecordsRequestHandler;
use Divergence\Models\ActiveRecord;
use Exception;
use Psr\Http\Message\ResponseInterface;

class MultiSave extends AbstractRecordsEndpoint
{
    protected RecordsRequestHandler $handler;

    public function __construct(RecordsRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        $className = $this->handler::$recordClass;

        $this->handler->prepareResponseModeJSON(['POST', 'PUT']);

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
                $results[] = $this->processDatumSave($datum);
            } catch (Exception $e) {
                $failed[] = [
                    'record' => $datum,
                    'errors' => $e->getMessage(),
                ];
            }
        }

        return $this->handler->respond($this->handler->getTemplateName($className::getPluralNoun()).'Saved', [
            'success' => count($results) || !count($failed),
            'data' => $results,
            'failed' => $failed,
        ]);
    }

    protected function getDatumRecord($datum)
    {
        $className = $this->handler::$recordClass;
        $PrimaryKey = $className::getPrimaryKey();

        if (empty($datum[$PrimaryKey])) {
            $defaultClass = $className::getDefaultClassName();
            $record = new $defaultClass();
            $this->handler->onRecordCreatedHook($record, $datum);

            return $record;
        }

        if (!$record = $className::getByID($datum[$PrimaryKey])) {
            throw new Exception('Record not found');
        }

        return $record;
    }

    protected function processDatumSave($datum)
    {
        $Record = $this->getDatumRecord($datum);

        if (!$this->handler->checkWriteAccess($Record)) {
            throw new Exception('Write access denied');
        }

        $this->handler->applyRecordDelta($Record, $datum);
        $this->handler->onBeforeRecordValidatedHook($Record, $datum);
        $this->handler->onBeforeRecordSavedHook($Record, $datum);

        $Record->save();

        $this->handler->onRecordSavedHook($Record, $datum);

        return (!$Record::fieldExists('Class') || get_class($Record) == $Record->Class) ? $Record : $Record->changeClass();
    }
}
