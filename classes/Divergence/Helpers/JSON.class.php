<?php
namespace Divergence\Helpers;

class JSON
{

	public static function getRequestData($subkey = false)
	{
		if(!$requestText = file_get_contents('php://input'))
		{
			return false;
		}
		
		$data = json_decode($requestText, true);
		
		return $subkey ? $data[$subkey] : $data;
	}
		
	public static function respond($data)
	{
		header('Content-type: application/json', true);
		print json_encode($data);
		exit;
	}
	
	public static function translateAndRespond($data)
	{
		/*
		if(isset($data['data']))
		{
			$data['data'] = static::translateObjects($data['data']);
		}

		static::respond($data);
		*/
		
		static::respond(static::translateObjects($data));
	}

	
	public static function error($message)
	{
		$args = func_get_args();
		
		self::respond(array(
			'success' => false
			,'message' => vsprintf($message, array_slice($args, 1))
		));
	}
	
	public static function translateObjects($input, $summary = false)
	{
		//Debug::dump($input, 'translating');
		
		if(is_object($input))
		{
			if(method_exists($input, 'getData'))
			{
				return $input->getData();
			}
			elseif(!$summary && ($data = $input->data) )
			{
				return self::translateObjects($data);
			}
			elseif($data = $input->summaryData)
			{
				return self::translateObjects($data, true);
			}
			
			
			return $input;
		}
		elseif(is_array($input))
		{
			foreach ($input AS &$item)
			{
				$item = static::translateObjects($item, $summary);
			}
			
			return $input;
		}
		else
		{
			return $input;
		}
	}
	
	public static function translateRecords($records, $useKeys = false)
	{
		$results = array();
		foreach($records AS $key => $record)
		{
			$record = $record->JsonTranslation;
			
			if (isset($record))
			{
				if($useKeys)
				{
					$results[$key] = $record;
				}
				else
				{
					$results[] = $record;
				}
			}
			else
			{
				throw new Exception('translateRecords: target does not have JsonTranslation');
			}
		}
		
		return $results;
	}
	
	public static function mapArrayToRecords($array)
	{
		return array_map(create_function('$value', 'return array($value);'), $array);
	}
	
	
	static public function indent($json) {
 
		$result	   = '';
		$pos	   = 0;
		$strLen	   = strlen($json);
		$indentStr = "\t";
		$newLine   = "\n";
	 
		for($i = 0; $i <= $strLen; $i++) {
			
			// Grab the next character in the string
			$char = substr($json, $i, 1);
			
			// If this character is the end of an element, 
			// output a new line and indent the next line
			if($char == '}' || $char == ']') {
				$result .= $newLine;
				$pos --;
				for ($j=0; $j<$pos; $j++) {
					$result .= $indentStr;
				}
			}
			
			// Add the character to the result string
			$result .= $char;
	 
			// If the last character was the beginning of an element, 
			// output a new line and indent the next line
			if ($char == ',' || $char == '{' || $char == '[') {
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos ++;
				}
				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
		}
	 
		return $result;
	}
	

}