<?php
namespace Divergence\Helpers;
	
class JSONP
{
	public static function respond($data)
	{
		
		
		$JSON = json_encode($data,JSON_UNESCAPED_UNICODE && JSON_HEX_TAG);
		
		if(!$JSON)
		{
			throw new Exception(json_last_error_msg());	
		}
		header('Content-type: application/javascript; charset=utf-8', true);
		echo 'var data = ' . $JSON;
		exit;
	}
	
	public static function translateAndRespond($data)
	{
		
		static::respond(JSON::translateObjects($data));
	}
}