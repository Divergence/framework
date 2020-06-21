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

use Exception;
use Divergence\Helpers\JSON;
use Divergence\Responders\Response;
use Divergence\Responders\JsonBuilder;
use Divergence\Responders\TwigBuilder;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Responders\JsonpBuilder;
use Psr\Http\Message\ResponseInterface;
use Divergence\Responders\ResponseBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Divergence\Models\ActiveRecord as ActiveRecord;

/**
 * RecordsRequestHandler - A REST API for Divergence ActiveRecord
 *
 * @package Divergence
 * @author  Henry Paradiz <henry.paradiz@gmail.com>
 * @author  Chris Alfano <themightychris@gmail.com>
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
        $this->responseBuilder = TwigBuilder::class;
    }

    /**
     * Start of routing for this controller.
     * Methods in this execution path will always respond either as an error or a normal response.
     * Responsible for detecting JSON or JSONP response modes.
     *
     * @return void
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

        if (method_exists($className, 'getByHandle')) {
            return $className::getByHandle($handle);
        }
    }

    public function prepareBrowseConditions($conditions = [])
    {
        if ($this->browseConditions) {
            if (!is_array($this->browseConditions)) {
                $this->browseConditions = [$this->browseConditions];
            }
            $conditions = array_merge($this->browseConditions, $conditions);
        }
        return $conditions;
    }

    public function prepareDefaultBrowseOptions()
    {
        if (isset($_REQUEST['offset'])) {
            if (isset($_REQUEST['start'])) {
                if (is_numeric($_REQUEST['start'])) {
                    $_REQUEST['offset'] = $_REQUEST['start'];
                }
            }
        }

        $limit = !empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? $_REQUEST['limit'] : $this->browseLimitDefault;
        $offset = !empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : false;

        $options = [
            'limit' =>  $limit,
            'offset' => $offset,
            'order' => $this->browseOrder,
        ];

        return $options;
    }

    public function handleBrowseRequest($options = [], $conditions = [], $responseID = null, $responseData = [])
    {
        if (!$this->checkBrowseAccess(func_get_args())) {
            return $this->throwUnauthorizedError();
        }

        $conditions = $this->prepareBrowseConditions($conditions);

        $options = $this->prepareDefaultBrowseOptions();

        // process sorter
        if (!empty($_REQUEST['sort'])) {
            $sort = json_decode($_REQUEST['sort'], true);
            if (!$sort || !is_array($sort)) {
                return $this->respond('error', [
                    'success' => false,
                    'failed' => [
                        'errors'	=>	'Invalid sorter.',
                    ],
                ]);
            }

            if (is_array($sort)) {
                foreach ($sort as $field) {
                    $options['order'][$field['property']] = $field['direction'];
                }
            }
        }

        // process filter
        if (!empty($_REQUEST['filter'])) {
            $filter = json_decode($_REQUEST['filter'], true);
            if (!$filter || !is_array($filter)) {
                return $this->respond('error', [
                    'success' => false,
                    'failed' => [
                        'errors'	=>	'Invalid filter.',
                    ],
                ]);
            }

            foreach ($filter as $field) {
                $conditions[$field['property']] = $field['value'];
            }
        }

        $className = static::$recordClass;

        return $this->respond(
            isset($responseID) ? $responseID : $this->getTemplateName($className::$pluralNoun),
            array_merge($responseData, [
                'success' => true,
                'data' => $className::getAllByWhere($conditions, $options),
                'conditions' => $conditions,
                'total' => DB::foundRows(),
                'limit' => $options['limit'],
                'offset' => $options['offset'],
            ])
        );
    }


    public function handleRecordRequest(ActiveRecord $Record, $action = false)
    {
        if (!$this->checkReadAccess($Record)) {
            return $this->throwUnauthorizedError();
        }

        switch ($action ? $action : $action = $this->shiftPath()) {
            case '':
            case false:
            {
                $className = static::$recordClass;

                return $this->respond($this->getTemplateName($className::$singularNoun), [
                    'success' => true,
                    'data' => $Record,
                ]);
            }

            case 'edit':
            {
                return $this->handleEditRequest($Record);
            }

            case 'delete':
            {
                return $this->handleDeleteRequest($Record);
            }

            default:
            {
                return $this->onRecordRequestNotHandled($Record, $action);
            }
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

    public function getDatumRecord($datum)
    {
        $className = static::$recordClass;
        $PrimaryKey = $className::getPrimaryKey();
        if (empty($datum[$PrimaryKey])) {
            $record = new $className::$defaultClass();
            $this->onRecordCreated($record, $datum);
        } else {
            if (!$record = $className::getByID($datum[$PrimaryKey])) {
                throw new Exception('Record not found');
            }
        }
        return $record;
    }

    public function processDatumSave($datum)
    {
        // get record
        $Record = $this->getDatumRecord($datum);

        // check write access
        if (!$this->checkWriteAccess($Record)) {
            throw new Exception('Write access denied');
        }

        // apply delta
        $this->applyRecordDelta($Record, $datum);

        // call template function
        $this->onBeforeRecordValidated($Record, $datum);

        // try to save record
        try {
            // call template function
            $this->onBeforeRecordSaved($Record, $datum);

            $Record->save();

            // call template function
            $this->onRecordSaved($Record, $datum);

            return (!$Record::fieldExists('Class') || get_class($Record) == $Record->Class) ? $Record : $Record->changeClass();
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function handleMultiSaveRequest(): ResponseInterface
    {
        $className = static::$recordClass;

        $this->prepareResponseModeJSON(['POST','PUT']);

        if ($className::fieldExists(key($_REQUEST['data']))) {
            $_REQUEST['data'] = [$_REQUEST['data']];
        }

        if (empty($_REQUEST['data']) || !is_array($_REQUEST['data'])) {
            return $this->respond('error', [
                'success' => false,
                'failed' => [
                    'errors'	=>	'Save expects "data" field as array of records.',
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
                continue;
            }
        }


        return $this->respond($this->getTemplateName($className::$pluralNoun).'Saved', [
            'success' => count($results) || !count($failed),
            'data' => $results,
            'failed' => $failed,
        ]);
    }

    public function processDatumDestroy($datum)
    {
        $className = static::$recordClass;
        $PrimaryKey = $className::getPrimaryKey();

        // get record
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

        // check write access
        if (!$this->checkWriteAccess($Record)) {
            throw new Exception('Write access denied');
        }

        if ($Record->destroy()) {
            return $Record;
        } else {
            throw new Exception('Destroy failed');
        }
    }

    public function handleMultiDestroyRequest(): ResponseInterface
    {
        $className = static::$recordClass;

        $this->prepareResponseModeJSON(['POST','PUT','DELETE']);

        if ($className::fieldExists(key($_REQUEST['data']))) {
            $_REQUEST['data'] = [$_REQUEST['data']];
        }

        if (empty($_REQUEST['data']) || !is_array($_REQUEST['data'])) {
            return $this->respond('error', [
                'success' => false,
                'failed' => [
                    'errors'	=>	'Save expects "data" field as array of records.',
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
                continue;
            }
        }

        return $this->respond($this->getTemplateName($className::$pluralNoun).'Destroyed', [
            'success' => count($results) || !count($failed),
            'data' => $results,
            'failed' => $failed,
        ]);
    }


    public function handleCreateRequest(ActiveRecord $Record = null): ResponseInterface
    {
        // save static class
        $this->calledClass = get_called_class();

        if (!$Record) {
            $className = static::$recordClass;
            $Record = new $className::$defaultClass();
        }

        // call template function
        $this->onRecordCreated($Record, $_REQUEST);

        return $this->handleEditRequest($Record);
    }

    public function handleEditRequest(ActiveRecord $Record): ResponseInterface
    {
        $className = static::$recordClass;

        if (!$this->checkWriteAccess($Record)) {
            return $this->throwUnauthorizedError();
        }

        if (in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT'])) {
            if ($this->responseBuilder === JsonBuilder::class) {
                $_REQUEST = JSON::getRequestData();
                if (is_array($_REQUEST['data'])) {
                    $_REQUEST = $_REQUEST['data'];
                }
            }
            $_REQUEST = $_REQUEST ? $_REQUEST : $_POST;

            // apply delta
            $this->applyRecordDelta($Record, $_REQUEST);

            // call template function
            $this->onBeforeRecordValidated($Record, $_REQUEST);

            // validate
            if ($Record->validate()) {
                // call template function
                $this->onBeforeRecordSaved($Record, $_REQUEST);

                try {
                    // save session
                    $Record->save();
                } catch (Exception $e) {
                    return $this->respond('Error', [
                        'success' => false,
                        'failed' => [
                            'errors'	=>	$e->getMessage(),
                        ],
                    ]);
                }

                // call template function
                $this->onRecordSaved($Record, $_REQUEST);

                // fire created response
                $responseID = $this->getTemplateName($className::$singularNoun).'Saved';
                $responseData = [
                    'success' => true,
                    'data' => $Record,
                ];
                return $this->respond($responseID, $responseData);
            }

            // fall through back to form if validation failed
        }

        $responseID = $this->getTemplateName($className::$singularNoun).'Edit';
        $responseData = [
            'success' => false,
            'data' => $Record,
        ];

        return $this->respond($responseID, $responseData);
    }


    public function handleDeleteRequest(ActiveRecord $Record): ResponseInterface
    {
        $className = static::$recordClass;

        if (!$this->checkWriteAccess($Record)) {
            return $this->throwUnauthorizedError();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = $Record->data;
            $Record->destroy();

            // call cleanup function after delete
            $this->onRecordDeleted($Record, $data);

            // fire created response
            return $this->respond($this->getTemplateName($className::$singularNoun).'Deleted', [
                'success' => true,
                'data' => $Record,
            ]);
        }

        return $this->respond('confirm', [
            'question' => 'Are you sure you want to delete this '.$className::$singularNoun.'?',
            'data' => $Record,
        ]);
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

    public function throwUnauthorizedError()
    {
        return $this->respond('Unauthorized', [
            'success' => false,
            'failed' => [
                'errors'	=>	'Login required.',
            ],
        ]);
    }

    public function throwAPIUnAuthorizedError()
    {
        return $this->respond('Unauthorized', [
            'success' => false,
            'failed' => [
                'errors'	=>	'API access required.',
            ],
        ]);
    }

    public function throwNotFoundError()
    {
        return $this->respond('error', [
            'success' => false,
            'failed' => [
                'errors'	=>	'Record not found.',
            ],
        ]);
    }

    public function onRecordRequestNotHandled(ActiveRecord $Record, $action)
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
}
