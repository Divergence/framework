<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Controllers;

use Divergence\Controllers\Records\Endpoints\Browse;
use Divergence\Controllers\Records\Endpoints\Create;
use Divergence\Controllers\Records\Endpoints\Delete;
use Divergence\Controllers\Records\Endpoints\Edit;
use Divergence\Controllers\Records\Endpoints\MultiDestroy;
use Divergence\Controllers\Records\Endpoints\MultiSave;
use Divergence\Controllers\Records\Endpoints\Record;
use Divergence\Helpers\JSON;
use Divergence\IO\Database\Connections;
use Divergence\Responders\JsonBuilder;
use Divergence\Responders\TwigBuilder;
use Divergence\Responders\JsonpBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Divergence\Models\ActiveRecord as ActiveRecord;

/**
 * RecordsRequestHandler - A REST API for Divergence ActiveRecord
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 *
 * @method ResponseInterface handleBrowseRequest($options = [], $conditions = [], $responseID = null, $responseData = [])
 * @method ResponseInterface handleRecordRequest(ActiveRecord $Record, $action = false)
 * @method ResponseInterface handleMultiSaveRequest()
 * @method ResponseInterface handleMultiDestroyRequest()
 * @method ResponseInterface handleCreateRequest(ActiveRecord $Record = null)
 * @method ResponseInterface handleEditRequest(ActiveRecord $Record)
 * @method ResponseInterface handleDeleteRequest(ActiveRecord $Record)
 */
abstract class RecordsRequestHandler extends RequestHandler
{
    public $config;

    public static $recordClass;
    public $accountLevelRead = false;
    public $accountLevelBrowse = 'Staff';
    public $accountLevelWrite = 'Staff';
    public $accountLevelAPI = false;
    public $browseOrder = false;
    public $browseConditions = false;
    public $browseLimitDefault = false;
    public $editableFields = false;
    public $searchConditions = false;
    public $calledClass = __CLASS__;

    public function __construct()
    {
        $this->registerEndpointClasses();
        $this->responseBuilder = TwigBuilder::class;
    }

    protected function registerEndpointClasses(): void
    {
        $this->endpointClasses = [];
        $this->endpoints = [];

        foreach ([
            Browse::class,
            Record::class,
            MultiSave::class,
            MultiDestroy::class,
            Create::class,
            Edit::class,
            Delete::class,
        ] as $className) {
            $this->registerEndpointClass($className);
        }
    }

    /**
     * Start of routing for this controller.
     * Methods in this execution path will always respond either as an error or a normal response.
     * Responsible for detecting JSON or JSONP response modes.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // save static class
        $this->calledClass = get_called_class();

        // handle JSON requests
        if ($this->peekPath() == 'json') {
            $this->shiftPath();

            // check access for API response modes
            $this->responseBuilder = JsonBuilder::class;

            if (in_array($this->responseBuilder, [JsonBuilder::class,JsonpBuilder::class])) {
                if (!$this->checkAPIAccess()) {
                    return $this->throwAPIUnAuthorizedError();
                }
            }
        }

        return $this->handleRecordsRequest();
    }

    public function handleRecordsRequest($action = false): ResponseInterface
    {
        switch ($action ? $action : $action = $this->shiftPath()) {
            case 'save':
                {
                    return $this->handleMultiSaveRequest();
                }

            case 'destroy':
                {
                    return $this->handleMultiDestroyRequest();
                }

            case 'create':
                {
                    return $this->handleCreateRequest();
                }

            case '':
            case false:
                {
                    return $this->handleBrowseRequest();
                }

            default:
                {
                    if ($Record = $this->getRecordByHandle($action)) {
                        return $this->handleRecordRequest($Record);
                    } else {
                        return $this->throwRecordNotFoundError();
                    }
                }
        }
    }

    public function getRecordByHandle($handle)
    {
        $className = static::$recordClass;
        if (is_callable([$className, 'getByHandle'])) {
            return $className::getByHandle($handle);
        }
    }

    public function prepareResponseModeJSON($methods = [])
    {
        if ($this->responseBuilder === JsonBuilder::class && in_array($_SERVER['REQUEST_METHOD'], $methods)) {
            $JSONData = JSON::getRequestData();
            if (is_array($JSONData)) {
                $_REQUEST = $JSONData;
            }
        }
    }

    // access control template functions
    public function checkBrowseAccess($arguments)
    {
        return true;
    }

    public function checkReadAccess(ActiveRecord $Record)
    {
        return true;
    }

    public function checkWriteAccess(ActiveRecord $Record)
    {
        return true;
    }

    public function checkAPIAccess()
    {
        return true;
    }

    public function throwUnauthorizedError(): ResponseInterface
    {
        return $this->respond('Unauthorized', [
            'success' => false,
            'failed' => [
                'errors'	=>	'Login required.',
            ],
        ]);
    }

    public function throwAPIUnAuthorizedError(): ResponseInterface
    {
        return $this->respond('Unauthorized', [
            'success' => false,
            'failed' => [
                'errors'	=>	'API access required.',
            ],
        ]);
    }

    public function throwNotFoundError(): ResponseInterface
    {
        return $this->respond('error', [
            'success' => false,
            'failed' => [
                'errors'	=>	'Record not found.',
            ],
        ]);
    }

    public function onRecordRequestNotHandled(ActiveRecord $Record, $action): ResponseInterface
    {
        return $this->respond('error', [
            'success' => false,
            'failed' => [
                'errors'	=>	'Malformed request.',
            ],
        ]);
    }



    public function getTemplateName($noun)
    {
        return preg_replace_callback('/\s+([a-zA-Z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $noun);
    }

    public function applyRecordDelta(ActiveRecord $Record, $data)
    {
        if (is_array($this->editableFields)) {
            $Record->setFields(array_intersect_key($data, array_flip($this->editableFields)));
        } else {
            $Record->setFields($data);
        }
    }

    // event template functions
    protected function onRecordCreated(ActiveRecord $Record, $data)
    {
    }
    protected function onBeforeRecordValidated(ActiveRecord $Record, $data)
    {
    }
    protected function onBeforeRecordSaved(ActiveRecord $Record, $data)
    {
    }
    protected function onRecordDeleted(ActiveRecord $Record, $data)
    {
    }
    protected function onRecordSaved(ActiveRecord $Record, $data)
    {
    }

    protected function throwRecordNotFoundError()
    {
        return $this->throwNotFoundError();
    }

    public function onRecordCreatedHook(ActiveRecord $Record, $data): void
    {
        $this->onRecordCreated($Record, $data);
    }

    public function onBeforeRecordValidatedHook(ActiveRecord $Record, $data): void
    {
        $this->onBeforeRecordValidated($Record, $data);
    }

    public function onBeforeRecordSavedHook(ActiveRecord $Record, $data): void
    {
        $this->onBeforeRecordSaved($Record, $data);
    }

    public function onRecordDeletedHook(ActiveRecord $Record, $data): void
    {
        $this->onRecordDeleted($Record, $data);
    }

    public function onRecordSavedHook(ActiveRecord $Record, $data): void
    {
        $this->onRecordSaved($Record, $data);
    }

    public function throwRecordNotFoundResponse()
    {
        return $this->throwRecordNotFoundError();
    }
}
