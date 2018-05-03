<?php
namespace Divergence\Controllers;

use Divergence\Helpers\JSON;
use Divergence\Helpers\JSONP;
use Divergence\Helpers\Util as Util;
use Divergence\IO\Database\MySQL as DB;
use Divergence\Models\ActiveRecord as ActiveRecord;

abstract class RecordsRequestHandler extends RequestHandler
{
    public static $config;

    // configurables
    public static $recordClass;
    public static $accountLevelRead = false;
    public static $accountLevelBrowse = 'Staff';
    public static $accountLevelWrite = 'Staff';
    public static $accountLevelAPI = false;
    public static $browseOrder = false;
    public static $browseConditions = false;
    public static $browseLimitDefault = false;
    public static $editableFields = false;
    public static $searchConditions = false;
    
    public static $calledClass = __CLASS__;
    public static $responseMode = 'dwoo';
    
    public static function handleRequest()
    {
        // save static class
        static::$calledClass = get_called_class();
    
        // handle JSON requests
        if (static::peekPath() == 'json') {
            static::$responseMode = static::shiftPath();
        }
        
        return static::handleRecordsRequest();
    }


    public static function handleRecordsRequest($action = false)
    {
        switch ($action ? $action : $action = static::shiftPath()) {
            case 'save':
            {
                return static::handleMultiSaveRequest();
            }
            
            case 'destroy':
            {
                return static::handleMultiDestroyRequest();
            }
            
            case 'create':
            {
                return static::handleCreateRequest();
            }
            
            case '':
            case false:
            {
                return static::handleBrowseRequest();
            }

            default:
            {
                if ($Record = static::getRecordByHandle($action)) {
                    if (!static::checkReadAccess($Record)) {
                        return static::throwUnauthorizedError();
                    }

                    return static::handleRecordRequest($Record);
                } else {
                    return static::throwRecordNotFoundError($action);
                }
            }
        }
    }
    
    public static function getRecordByHandle($handle)
    {
        $className = static::$recordClass;
        
        if (method_exists($className, 'getByHandle')) {
            return $className::getByHandle($handle);
        } else {
            return null;
        }
    }
    
    public static function handleQueryRequest($query, $conditions = [], $options = [], $responseID = null, $responseData = [])
    {
        $terms = preg_split('/\s+/', $query);
        
        $options = Util::prepareOptions($options, [
            'limit' =>  !empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? $_REQUEST['limit'] : static::$browseLimitDefault
            ,'offset' => !empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : false
            ,'order' => ['searchScore DESC'],
        ]);

        $select = ['*'];
        $having = [];
        $matchers = [];

        foreach ($terms as $term) {
            $n = 0;
            $qualifier = 'any';
            $split = explode(':', $term, 2);
            
            if (count($split) == 2) {
                $qualifier = strtolower($split[0]);
                $term = $split[1];
            }
            
            foreach (static::$searchConditions as $k => $condition) {
                if (!in_array($qualifier, $condition['qualifiers'])) {
                    continue;
                }

                $matchers[] = [
                    'condition' => sprintf($condition['sql'], DB::escape($term))
                    ,'points' => $condition['points'],
                ];
                
                $n++;
            }
            
            if ($n == 0) {
                throw new Exception('Unknown search qualifier: '.$qualifier);
            }
        }
        
        $select[] = join('+', array_map(function ($c) {
            return sprintf('IF(%s, %u, 0)', $c['condition'], $c['points']);
        }, $matchers)) . ' AS searchScore';
        
        $having[] = 'searchScore > 1';
    
        $className = static::$recordClass;

        return static::respond(
            isset($responseID) ? $responseID : static::getTemplateName($className::$pluralNoun),
            array_merge($responseData, [
                'success' => true
                ,'data' => $className::getAllByQuery(
                    'SELECT %s FROM `%s` WHERE (%s) %s %s %s',
                    [
                        join(',', $select)
                        ,$className::$tableName
                        ,$conditions ? join(') AND (', $className::mapConditions($conditions)) : '1'
                        ,count($having) ? 'HAVING ('.join(') AND (', $having).')' : ''
                        ,count($options['order']) ? 'ORDER BY '.join(',', $options['order']) : ''
                        ,$options['limit'] ? sprintf('LIMIT %u,%u', $options['offset'], $options['limit']) : '',
                    ]
                )
                ,'query' => $query
                ,'conditions' => $conditions
                ,'total' => DB::foundRows()
                ,'limit' => $options['limit']
                ,'offset' => $options['offset'],
            ])
        );
    }


    public static function handleBrowseRequest($options = [], $conditions = [], $responseID = null, $responseData = [])
    {
        if (!static::checkBrowseAccess(func_get_args())) {
            return static::throwUnauthorizedError();
        }
            
        if (static::$browseConditions) {
            if (!is_array(static::$browseConditions)) {
                static::$browseConditions = [static::$browseConditions];
            }
            $conditions = array_merge(static::$browseConditions, $conditions);
        }
        
        if (empty($_REQUEST['offset']) && is_numeric($_REQUEST['start'])) {
            $_REQUEST['offset'] = $_REQUEST['start'];
        }
        
        $limit = !empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? $_REQUEST['limit'] : static::$browseLimitDefault;
        $offset = !empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : false;
        
        $options = Util::prepareOptions($options, [
            'limit' =>  $limit
            ,'offset' => $offset
            ,'order' => static::$browseOrder,
        ]);
        
        // process sorter
        if (!empty($_REQUEST['sort'])) {
            $sort = json_decode($_REQUEST['sort'], true);
            if (!$sort || !is_array($sort)) {
                //throw new Exception('Invalid sorter');
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
                throw new Exception('Invalid filter');
            }

            foreach ($filter as $field) {
                if ($_GET['anyMatch']) {
                    $conditions[$field['property']] = [
                        'value'	=>	'%' . $field['value'] . '%'
                        ,'operator' => 'LIKE',
                    ];
                } else {
                    $conditions[$field['property']] = $field['value'];
                }
            }
            
            if ($_GET['anyMatch']) {
                foreach ($conditions as $key=>$condition) {
                    $where[] = '`' . $key . "` LIKE '" . $condition['value'] . "'";
                }
                
                $conditions = [implode(' OR ', $where)];
            }
        }
        

        // handle query search
        if (!empty($_REQUEST['q']) && static::$searchConditions) {
            return static::handleQueryRequest($_REQUEST['q'], $conditions, ['limit' => $limit, 'offset' => $offset], $responseID, $responseData);
        }

        $className = static::$recordClass;

        return static::respond(
            isset($responseID) ? $responseID : static::getTemplateName($className::$pluralNoun),
            array_merge($responseData, [
                'success' => true
                ,'data' => $className::getAllByWhere($conditions, $options)
                ,'conditions' => $conditions
                ,'total' => DB::foundRows()
                ,'limit' => $options['limit']
                ,'offset' => $options['offset'],
            ])
        );
    }


    public static function handleRecordRequest(ActiveRecord $Record, $action = false)
    {
        switch ($action ? $action : $action = static::shiftPath()) {
            case '':
            case false:
            {
                $className = static::$recordClass;
                
                return static::respond(static::getTemplateName($className::$singularNoun), [
                    'success' => true
                    ,'data' => $Record,
                ]);
            }
            
            case 'edit':
            {
                return static::handleEditRequest($Record);
            }
            
            case 'delete':
            {
                return static::handleDeleteRequest($Record);
            }
        
            default:
            {
                return static::onRecordRequestNotHandled($Record, $action);
            }
        }
    }



    public static function handleMultiSaveRequest()
    {
        $className = static::$recordClass;
    
        $PrimaryKey = $className::$primaryKey ? $className::$primaryKey : 'ID';
        
        if (static::$responseMode == 'json' && in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT'])) {
            $_REQUEST = JSON::getRequestData();
            
            if ($_REQUEST['data'][$PrimaryKey]) {
                $_REQUEST['data'] = [$_REQUEST['data']];
            }
        }
                
        if (empty($_REQUEST['data']) || !is_array($_REQUEST['data'])) {
            return static::throwInvalidRequestError('Save expects "data" field as array of record deltas');
        }
        
        
        $results = [];
        $failed = [];

        foreach ($_REQUEST['data'] as $datum) {
            // get record
            if (empty($datum[$PrimaryKey])) {
                $Record = new $className::$defaultClass();
                static::onRecordCreated($Record, $datum);
            } else {
                if (!$Record = $className::getByID($datum[$PrimaryKey])) {
                    return static::throwRecordNotFoundError($datum[$PrimaryKey]);
                }
            }
            
            // check write access
            if (!static::checkWriteAccess($Record)) {
                $failed[] = [
                    'record' => $datum
                    ,'errors' => 'Write access denied',
                ];
                continue;
            }
            
            // apply delta
            static::applyRecordDelta($Record, $datum);

            // call template function
            static::onBeforeRecordValidated($Record, $datum);

            // try to save record
            try {
                // call template function
                static::onBeforeRecordSaved($Record, $datum);

                $Record->save();
                $results[] = (!$Record::fieldExists('Class') || get_class($Record) == $Record->Class) ? $Record : $Record->changeClass();
                
                // call template function
                static::onRecordSaved($Record, $datum);
            } catch (RecordValidationException $e) {
                $failed[] = [
                    'record' => $Record->data
                    ,'validationErrors' => $Record->validationErrors,
                ];
            }
        }
        
        
        return static::respond(static::getTemplateName($className::$pluralNoun).'Saved', [
            'success' => count($results) || !count($failed)
            ,'data' => $results
            ,'failed' => $failed,
        ]);
    }
    
    
    public static function handleMultiDestroyRequest()
    {
        if (static::$responseMode == 'json' && in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','DELETE'])) {
            $_REQUEST = JSON::getRequestData();
        }
                
        if (empty($_REQUEST['data']) || !is_array($_REQUEST['data'])) {
            return static::throwInvalidRequestError('Handler expects "data" field as array');
        }
        
        $className = static::$recordClass;
        $results = [];
        $failed = [];
        
        foreach ($_REQUEST['data'] as $datum) {
            // get record
            if (is_numeric($datum)) {
                $recordID = $datum;
            } elseif (!empty($datum['ID']) && is_numeric($datum['ID'])) {
                $recordID = $datum['ID'];
            } else {
                $failed[] = [
                    'record' => $datum
                    ,'errors' => 'ID missing',
                ];
                continue;
            }

            if (!$Record = $className::getByID($recordID)) {
                $failed[] = [
                    'record' => $datum
                    ,'errors' => 'ID not found',
                ];
                continue;
            }
            
            // check write access
            if (!static::checkWriteAccess($Record)) {
                $failed[] = [
                    'record' => $datum
                    ,'errors' => 'Write access denied',
                ];
                continue;
            }
        
            // destroy record
            if ($Record->destroy()) {
                $results[] = $Record;
            }
        }
        
        return static::respond(static::getTemplateName($className::$pluralNoun).'Destroyed', [
            'success' => count($results) || !count($failed)
            ,'data' => $results
            ,'failed' => $failed,
        ]);
    }


    public static function handleCreateRequest(ActiveRecord $Record = null)
    {
        // save static class
        static::$calledClass = get_called_class();

        if (!$Record) {
            $className = static::$recordClass;
            $Record = new $className::$defaultClass();
        }
        
        // call template function
        static::onRecordCreated($Record, $_REQUEST);

        return static::handleEditRequest($Record);
    }

    public static function handleEditRequest(ActiveRecord $Record)
    {
        if (static::$responseMode == 'json' && in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT'])) {
            $_REQUEST = JSON::getRequestData();
            if (is_array($_REQUEST['data'])) {
                $_REQUEST = $_REQUEST['data'];
            }
        }
    
        $className = static::$recordClass;

        if (!static::checkWriteAccess($Record)) {
            return static::throwUnauthorizedError();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_REQUEST = $_REQUEST ? $_REQUEST : $_POST;
        
            // apply delta
            static::applyRecordDelta($Record, $_REQUEST);
            
            // call template function
            static::onBeforeRecordValidated($Record, $_REQUEST);

            // validate
            if ($Record->validate()) {
                // call template function
                static::onBeforeRecordSaved($Record, $_REQUEST);
                
                try {
                    // save session
                    $Record->save();
                } catch (Exception $e) {
                    return static::respond('Error', [
                        'success' => false
                        ,'failed' => [
                            'errors'	=>	$e->getMessage(),
                        ],
                    ]);
                }
                
                // call template function
                static::onRecordSaved($Record, $_REQUEST);
        
                // fire created response
                $responseID = static::getTemplateName($className::$singularNoun).'Saved';
                $responseData = static::getEditResponse($responseID, [
                    'success' => true
                    ,'data' => $Record,
                ]);
                return static::respond($responseID, $responseData);
            }
            
            // fall through back to form if validation failed
        }
        
        $responseID = static::getTemplateName($className::$singularNoun).'Edit';
        $responseData = static::getEditResponse($responseID, [
            'success' => false
            ,'data' => $Record,
        ]);
    
        return static::respond($responseID, $responseData);
    }


    public static function handleDeleteRequest(ActiveRecord $Record)
    {
        $className = static::$recordClass;

        if (!static::checkWriteAccess($Record)) {
            return static::throwUnauthorizedError();
        }
    
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $Record->destroy();
                    
            // call cleanup function after delete
            $data = ['Context' => 'Product', 'id' => $Record->id];
            static::onRecordDeleted($Record, $data);
            
            // fire created response
            return static::respond(static::getTemplateName($className::$singularNoun).'Deleted', [
                'success' => true
                ,'data' => $Record,
            ]);
        }
    
        return static::respond('confirm', [
            'question' => 'Are you sure you want to delete this '.$className::$singularNoun.'?'
            ,'data' => $Record,
        ]);
    }
    
    
    public static function respond($responseID, $responseData = [], $responseMode = false)
    {
        // default to static property
        if (!$responseMode) {
            $responseMode = static::$responseMode;
        }
        
        // check access for API response modes
        if ($responseMode != 'html' && $responseMode != 'return') {
            if (!static::checkAPIAccess($responseID, $responseData, $responseMode)) {
                return static::throwAPIUnauthorizedError();
            }
        }
    
        return parent::respond($responseID, $responseData, $responseMode);
    }
    
    // access control template functions
    public static function checkBrowseAccess($arguments)
    {
        switch (static::$accountLevelBrowse) {
            
            case 'Staff':
            {
                return true;
            }
            case 'Guest':
                return true;
            
            default:
                return false;
        }
        
        return true;
    }

    public static function checkReadAccess(ActiveRecord $Record)
    {
        switch (static::$accountLevelRead) {
            case 'Staff':
            {
                
            
                return true;
            }
            case 'Guest':
                return true;
            
            default:
                return true;
        }
        
        return true;
    }
    
    public static function checkWriteAccess(ActiveRecord $Record)
    {
        switch (static::$accountLevelWrite) {
            case 'Staff':
            {
            
            
                if (empty($_SESSION['username'])) {
                    return false;
                }
                
                return true;
            }
            
            case 'Guest':
                return true;
            
            default:
                return false;
        }
        
        return true;
    }
    
    public static function checkAPIAccess($responseID, $responseData, $responseMode)
    {
        switch (static::$accountLevelAPI) {
            case 'Staff':
            {
                
            
                if (empty($_SESSION['username'])) {
                    return false;
                }
                return true;
            }
            
            case 'Guest':
                return true;
            
            default:
                return true;
        }
        
        return true;
    }
    
    public static function throwUnauthorizedError()
    {
        if (static::$responseMode == 'json') {
            return static::respond('Unauthorized', [
                'success' => false
                ,'failed' => [
                    'errors'	=>	'Login required.',
                ],
            ]);
        }
    }
    
    protected static function onRecordRequestNotHandled(ActiveRecord $Record, $action)
    {
        return static::throwNotFoundError();
    }
    


    protected static function getTemplateName($noun)
    {
        return preg_replace_callback('/\s+([a-zA-Z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $noun);
    }
    
    protected static function applyRecordDelta(ActiveRecord $Record, $data)
    {
        if (static::$editableFields) {
            $Record->setFields(array_intersect_key($data, array_flip(static::$editableFields)));
        } else {
            return $Record->setFields($data);
        }
    }
    
    // event template functions
    protected static function onRecordCreated(ActiveRecord $Record, $data)
    {
    }
    protected static function onBeforeRecordValidated(ActiveRecord $Record, $data)
    {
    }
    protected static function onBeforeRecordSaved(ActiveRecord $Record, $data)
    {
    }
    protected static function onRecordDeleted(ActiveRecord $Record, $data)
    {
    }
    protected static function onRecordSaved(ActiveRecord $Record, $data)
    {
    }
    
    protected static function getEditResponse($responseID, $responseData)
    {
        return $responseData;
    }
    
    
    protected static function throwRecordNotFoundError($handle, $message = 'Record not found')
    {
        return static::throwNotFoundError($message);
    }
}
