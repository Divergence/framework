<?php
namespace Divergence\Helpers;

class JSON
{
    public static function getRequestData($subkey = false)
    {
        if (!$requestText = file_get_contents('php://input')) {
            return false;
        }
        
        $data = json_decode($requestText, true);
        
        return $subkey ? $data[$subkey] : $data;
    }
        
    public static function respond($data)
    {
        header('Content-type: application/json', true);
        echo json_encode($data);
        exit;
    }
    
    public static function translateAndRespond($data)
    {
        static::respond(static::translateObjects($data));
    }

    
    public static function error($message)
    {
        $args = func_get_args();
        
        self::respond([
            'success' => false
            ,'message' => vsprintf($message, array_slice($args, 1)),
        ]);
    }
    
    public static function translateObjects($input, $summary = false)
    {
        if (is_object($input)) {
            if (method_exists($input, 'getData')) {
                return $input->getData();
            } elseif (!$summary && ($data = $input->data)) {
                return self::translateObjects($data);
            } elseif ($data = $input->summaryData) {
                return self::translateObjects($data, true);
            }
            return $input;
        } elseif (is_array($input)) {
            foreach ($input as &$item) {
                $item = static::translateObjects($item, $summary);
            }
            return $input;
        } else {
            return $input;
        }
    }
}
