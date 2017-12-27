<?php
namespace Divergence\Controllers;

use Divergence\IO\Database\MySQL as DB;
use Divergence\Helpers\Util as Util;

abstract class RecordsRequestHandler extends RequestHandler
{

	// configurables
	static public $recordClass;
	static public $accountLevelRead = false;
	static public $accountLevelBrowse = 'Staff';
	static public $accountLevelWrite = 'Staff';
	static public $accountLevelAPI = false;
	static public $browseOrder = false;
	static public $browseConditions = false;
	static public $browseLimitDefault = false;
	static public $editableFields = false;
	static public $searchConditions = false;
	
	static public $calledClass = __CLASS__;
	static public $responseMode = 'html';
	
	static public function handleRequest()
	{
		// save static class
		static::$calledClass = get_called_class();
	
		// handle JSON requests
		if(static::peekPath() == 'json')
		{
			static::$responseMode = static::shiftPath();
		}
		
		return static::handleRecordsRequest();
	}


	static public function handleRecordsRequest($action = false)
	{
		switch($action ? $action : $action = static::shiftPath())
		{
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
				if($Record = static::getRecordByHandle($action))
				{
					if(!static::checkReadAccess($Record))
					{
						return static::throwUnauthorizedError();
					}

					return static::handleRecordRequest($Record);
				}
				else
				{
					return static::throwRecordNotFoundError($action);
				}
			}
		}
	}
	
	static public function getRecordByHandle($handle)
	{
		$className = static::$recordClass;
		
		if(method_exists($className, 'getByHandle'))
			return $className::getByHandle($handle);
		else
			return null;
	}
	
	static public function handleQueryRequest($query, $conditions = array(), $options = array(), $responseID = null, $responseData = array())
	{
		$terms = preg_split('/\s+/', $query);
		
		$options = Util::prepareOptions($options, array(
			'limit' =>  !empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? $_REQUEST['limit'] : static::$browseLimitDefault
			,'offset' => !empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : false
			,'order' => array('searchScore DESC')
		));

		$select = array('*');
		$having = array();
		$matchers = array();

		foreach($terms AS $term)
		{
			$n = 0;
			$qualifier = 'any';
			$split = explode(':', $term, 2);
			
			if(count($split) == 2)
			{
				$qualifier = strtolower($split[0]);
				$term = $split[1];
			}
			
			foreach(static::$searchConditions AS $k => $condition)
			{
				if(!in_array($qualifier, $condition['qualifiers']))
					continue;

				$matchers[] = array(
					'condition' => sprintf($condition['sql'], DB::escape($term))
					,'points' => $condition['points']
				);
				
				$n++;
			}
			
			if($n == 0)
			{
				throw new Exception('Unknown search qualifier: '.$qualifier);
			}
		}
		
		$select[] = join('+', array_map(function($c) {
			return sprintf('IF(%s, %u, 0)', $c['condition'], $c['points']);
		}, $matchers)) . ' AS searchScore';
		
		$having[] = 'searchScore > 1';
	
		$className = static::$recordClass;

		return static::respond(
			isset($responseID) ? $responseID : static::getTemplateName($className::$pluralNoun)
			,array_merge($responseData, array(
				'success' => true
				,'data' => $className::getAllByQuery(
					'SELECT %s FROM `%s` WHERE (%s) %s %s %s'
					,array(
						join(',',$select)
						,$className::$tableName
						,$conditions ? join(') AND (',$className::mapConditions($conditions)) : '1'
						,count($having) ? 'HAVING ('.join(') AND (', $having).')' : ''
						,count($options['order']) ? 'ORDER BY '.join(',', $options['order']) : ''
						,$options['limit'] ? sprintf('LIMIT %u,%u',$options['offset'],$options['limit']) : ''
					)
				)
				,'query' => $query
				,'conditions' => $conditions
			    ,'total' => DB::foundRows()
			    ,'limit' => $options['limit']
			    ,'offset' => $options['offset']
			))
		);
	}


	static public function handleBrowseRequest($options = array(), $conditions = array(), $responseID = null, $responseData = array())
	{
		if(!static::checkBrowseAccess(func_get_args()))
		{
			return static::throwUnauthorizedError();
		}
			
		if(static::$browseConditions)
		{
			if(!is_array(static::$browseConditions))
				static::$browseConditions = array(static::$browseConditions);
			$conditions = array_merge(static::$browseConditions, $conditions);
		}
		
		if(empty($_REQUEST['offset']) && is_numeric($_REQUEST['start']))
		{
			$_REQUEST['offset'] = $_REQUEST['start'];
		}
		
		$limit = !empty($_REQUEST['limit']) && is_numeric($_REQUEST['limit']) ? $_REQUEST['limit'] : static::$browseLimitDefault;
		$offset = !empty($_REQUEST['offset']) && is_numeric($_REQUEST['offset']) ? $_REQUEST['offset'] : false;
		
		$options = Util::prepareOptions($options, array(
			'limit' =>  $limit
			,'offset' => $offset
			,'order' => static::$browseOrder
		));
		
		// process sorter
		if(!empty($_REQUEST['sort']))
		{
			$sort = json_decode($_REQUEST['sort'],true);
			if(!$sort || !is_array($sort)) {
				//throw new Exception('Invalid sorter');
			}

			if(is_array($sort))
			{
				foreach($sort as $field) {
					$options['order'][$field['property']] = $field['direction'];
				}	
			}
		}
		
		// process filter
		if(!empty($_REQUEST['filter']))
		{		
			$filter = json_decode($_REQUEST['filter'], true);
			if(!$filter || !is_array($filter))
				throw new Exception('Invalid filter');

			foreach($filter AS $field)
			{
				if($_GET['anyMatch']) {
					$conditions[$field['property']] = array(
						'value'	=>	'%' . $field['value'] . '%'
						,'operator' => 'LIKE'
					);
				}
				else {
					$conditions[$field['property']] = $field['value'];
				}
			}
			
			if($_GET['anyMatch'])
			{
				foreach($conditions as $key=>$condition)
				{
					$where[] = '`' . $key . "` LIKE '" . $condition['value'] . "'";
				}
				
				$conditions = array(implode(' OR ', $where));
			}
		}
		

		// handle query search
		if(!empty($_REQUEST['q']) && static::$searchConditions)
		{
			return static::handleQueryRequest($_REQUEST['q'], $conditions, array('limit' => $limit, 'offset' => $offset), $responseID, $responseData);
		}

		$className = static::$recordClass;

		return static::respond(
			isset($responseID) ? $responseID : static::getTemplateName($className::$pluralNoun)
			,array_merge($responseData, array(
				'success' => true
				,'data' => $className::getAllByWhere($conditions, $options)
				,'conditions' => $conditions
			    ,'total' => DB::foundRows()
			    ,'limit' => $options['limit']
			    ,'offset' => $options['offset']
			))
		);
	}


	static public function handleRecordRequest(ActiveRecord $Record, $action = false)
	{
	
		switch($action ? $action : $action = static::shiftPath())
		{
			case '':
			case false:
			{
				$className = static::$recordClass;
				
				return static::respond(static::getTemplateName($className::$singularNoun), array(
					'success' => true
					,'data' => $Record
				));
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
	
	static protected function onRecordRequestNotHandled(ActiveRecord $Record, $action)
	{
		return static::throwNotFoundError();
	} 



	static public function handleMultiSaveRequest()
	{	
		$className = static::$recordClass;
	
		$PrimaryKey = $className::$primaryKey?$className::$primaryKey:'ID';
		
		if(static::$responseMode == 'json' && in_array($_SERVER['REQUEST_METHOD'], array('POST','PUT')))
		{
			$_REQUEST = JSON::getRequestData();
			
			if($_REQUEST['data'][$PrimaryKey])
			{
				$_REQUEST['data'] = array($_REQUEST['data']);
			}
		}
				
		if(empty($_REQUEST['data']) || !is_array($_REQUEST['data']))
		{
			return static::throwInvalidRequestError('Save expects "data" field as array of record deltas');
		}
		
		
		$results = array();
		$failed = array();

		foreach($_REQUEST['data'] AS $datum)
		{
			// get record
			if(empty($datum[$PrimaryKey]))
			{
				$Record = new $className::$defaultClass();
				static::onRecordCreated($Record, $datum);
			}
			else
			{
				if(!$Record = $className::getByID($datum[$PrimaryKey]))
				{
					return static::throwRecordNotFoundError($datum[$PrimaryKey]);
				}
			}
			
			// check write access
			if(!static::checkWriteAccess($Record))
			{
				$failed[] = array(
					'record' => $datum
					,'errors' => 'Write access denied'
				);
				continue;
			}
 			
			// apply delta
			static::applyRecordDelta($Record, $datum);

			// call template function
			static::onBeforeRecordValidated($Record, $datum);

			// try to save record
			try
			{
				// call template function
				static::onBeforeRecordSaved($Record, $datum);

				$Record->save();
				$results[] = (!$Record::_fieldExists('Class') || get_class($Record) == $Record->Class) ? $Record : $Record->changeClass();
				
				// call template function
				static::onRecordSaved($Record, $datum);
			}
			catch(RecordValidationException $e)
			{
				$failed[] = array(
					'record' => $Record->data
					,'validationErrors' => $Record->validationErrors
				);
			}
		}
		
		
		return static::respond(static::getTemplateName($className::$pluralNoun).'Saved', array(
			'success' => count($results) || !count($failed)
			,'data' => $results
			,'failed' => $failed
		));
	}
	
	
	static public function handleMultiDestroyRequest()
	{
		
		if(static::$responseMode == 'json' && in_array($_SERVER['REQUEST_METHOD'], array('POST','PUT','DELETE')))
		{
			$_REQUEST = JSON::getRequestData();
		}
				
		if(empty($_REQUEST['data']) || !is_array($_REQUEST['data']))
		{
			return static::throwInvalidRequestError('Handler expects "data" field as array');
		}
		
		$className = static::$recordClass;
		$results = array();
		$failed = array();
		
		foreach($_REQUEST['data'] AS $datum)
		{
			// get record
			if(is_numeric($datum))
			{
				$recordID = $datum;
			}
			elseif(!empty($datum['ID']) && is_numeric($datum['ID']))
			{
				$recordID = $datum['ID'];
			}
			else
			{
				$failed[] = array(
					'record' => $datum
					,'errors' => 'ID missing'
				);
				continue;
			}

			if(!$Record = $className::getByID($recordID))
			{
				$failed[] = array(
					'record' => $datum
					,'errors' => 'ID not found'
				);
				continue;
			}
			
			// check write access
			if(!static::checkWriteAccess($Record))
			{
				$failed[] = array(
					'record' => $datum
					,'errors' => 'Write access denied'
				);
				continue;
			}
		
			// destroy record
			if($Record->destroy())
			{
				$results[] = $Record;
			}
		}
		
		return static::respond(static::getTemplateName($className::$pluralNoun).'Destroyed', array(
			'success' => count($results) || !count($failed)
			,'data' => $results
			,'failed' => $failed
		));
	}


	static public function handleCreateRequest(ActiveRecord $Record = null)
	{
		// save static class
		static::$calledClass = get_called_class();

		if(!$Record)
		{
			$className = static::$recordClass;
			$Record = new $className::$defaultClass();
		}
		
		// call template function
		static::onRecordCreated($Record, $_REQUEST);

		return static::handleEditRequest($Record);
	}

	static public function handleEditRequest(ActiveRecord $Record)
	{
		if(static::$responseMode == 'json' && in_array($_SERVER['REQUEST_METHOD'], array('POST','PUT')))
		{
			$_REQUEST = JSON::getRequestData();
			if(is_array($_REQUEST['data']))
			{
				$_REQUEST = $_REQUEST['data'];
			}
		}
	
		$className = static::$recordClass;

		if(!static::checkWriteAccess($Record))
		{
			return static::throwUnauthorizedError();
		}

		if($_SERVER['REQUEST_METHOD'] == 'POST')
		{	
			$_REQUEST = $_REQUEST?$_REQUEST:$_POST;
		
			// apply delta
			static::applyRecordDelta($Record, $_REQUEST);
			
			// call template function
			static::onBeforeRecordValidated($Record, $_REQUEST);

			// validate
			if($Record->validate())
			{
				// call template function
				static::onBeforeRecordSaved($Record, $_REQUEST);
				
				try
				{
					// save session
					$Record->save();
				}
				catch(Exception $e)
				{
					return static::respond('Error', array(
						'success' => false
						,'failed' => array(
							'errors'	=>	$e->getMessage()
						)
					));
				}
				
				// call template function
				static::onRecordSaved($Record, $_REQUEST);
		
				// fire created response
				$responseID = static::getTemplateName($className::$singularNoun).'Saved';
				$responseData = static::getEditResponse($responseID, array(
					'success' => true
					,'data' => $Record
				));
				return static::respond($responseID, $responseData);
			}
			
			// fall through back to form if validation failed
		}
		
		$responseID = static::getTemplateName($className::$singularNoun).'Edit';
		$responseData = static::getEditResponse($responseID, array(
			'success' => false
			,'data' => $Record
		));
	
		return static::respond($responseID, $responseData);
	}


	static public function handleDeleteRequest(ActiveRecord $Record)
	{
		$className = static::$recordClass;

		if(!static::checkWriteAccess($Record))
		{
			return static::throwUnauthorizedError();
		}
	
		if($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			$Record->destroy();
					
			// call cleanup function after delete
			$data = array('Context' => 'Product', 'id' => $Record->id);
			static::onRecordDeleted($Record, $data);
			
			// fire created response
			return static::respond(static::getTemplateName($className::$singularNoun).'Deleted', array(
				'success' => true
				,'data' => $Record
			));
		}
	
		return static::respond('confirm', array(
			'question' => 'Are you sure you want to delete this '.$className::$singularNoun.'?'
			,'data' => $Record
		));
	}
	


	static protected function getTemplateName($noun)
	{
		return preg_replace_callback('/\s+([a-zA-Z])/', function($matches) { return strtoupper($matches[1]); }, $noun);
	}
	
	
	static public function respond($responseID, $responseData = array(), $responseMode = false)
	{
		// default to static property
		if(!$responseMode)
		{
			$responseMode = static::$responseMode;
		}
		
		// check access for API response modes
		if($responseMode != 'html' && $responseMode != 'return')
		{
			if(!static::checkAPIAccess($responseID, $responseData, $responseMode))
			{
				return static::throwAPIUnauthorizedError();
			}
		}
	
		return parent::respond($responseID, $responseData, $responseMode);
	}
	
	static protected function applyRecordDelta(ActiveRecord $Record, $data)
	{
		if(static::$editableFields)
		{
			$Record->setFields(array_intersect_key($data, array_flip(static::$editableFields)));
		}
		else
		{
			return $Record->setFields($data);
		}
	}
	
	// event template functions
	static protected function onRecordCreated(ActiveRecord $Record, $data)
	{
	}
	static protected function onBeforeRecordValidated(ActiveRecord $Record, $data)
	{
	}
	static protected function onBeforeRecordSaved(ActiveRecord $Record, $data)
	{
	}
	static protected function onRecordDeleted(ActiveRecord $Record, $data)
	{
	}
	static protected function onRecordSaved(ActiveRecord $Record, $data)
	{
	}
	
	static protected function getEditResponse($responseID, $responseData)
	{
		return $responseData;
	}
	
	// access control template functions
	static public function checkBrowseAccess($arguments)
	{
		switch(static::$accountLevelBrowse)
		{
			
			case 'Staff':
			{
				if(in_array($_GET['Key'],static::$config['recordKeys']))
				{
					return true;
				}
			
				if(empty($_SESSION['username']))
				{
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

	static public function checkReadAccess(ActiveRecord $Record)
	{
		switch(static::$accountLevelRead)
		{
			case 'Staff':
			{
				if(in_array($_GET['Key'],static::$config['recordKeys']))
				{
					return true;
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
	
	static public function checkWriteAccess(ActiveRecord $Record)
	{
		switch(static::$accountLevelWrite)
		{	
			case 'Staff':
			{
				if(in_array($_GET['Key'],static::$config['recordKeys']))
				{
					return true;
				}
			
				if(empty($_SESSION['username']))
				{
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
	
	static public function checkAPIAccess($responseID, $responseData, $responseMode)
	{
		switch(static::$accountLevelAPI)
		{
			case 'Staff':
			{
				if(in_array($_GET['Key'],static::$config['recordKeys']))
				{
					return true;
				}
			
				if(empty($_SESSION['username']))
				{
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
	
	
	static protected function throwRecordNotFoundError($handle, $message = 'Record not found')
	{
		return static::throwNotFoundError($message);
	}
	
	static public function throwUnauthorizedError()
	{	
		if(static::$responseMode == 'json')
		{
			return static::respond('Unauthorized', array(
				'success' => false
				,'failed' => array(
					'errors'	=>	'Login required.'
				)
			));
		}
	}

}