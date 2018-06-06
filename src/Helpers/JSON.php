<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Helpers;

/**
 * JSON
 *
 * @package Divergence
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 * @author  Chris Alfano <themightychris@gmail.com>
 */
class JSON
{
    public static $inputStream = 'php://input'; // this is a setting so that unit tests can provide a fake stream :)
    public static function getRequestData($subkey = false)
    {
        if (!$requestText = file_get_contents(static::$inputStream)) {
            return false;
        }

        $data = json_decode($requestText, true);

        return $subkey ? $data[$subkey] : $data;
    }

    public static function respond($data)
    {
        header('Content-type: application/json', true);
        echo json_encode($data);
    }

    public static function translateAndRespond($data)
    {
        static::respond(static::translateObjects($data));
    }

    public static function error($message)
    {
        static::respond([
            'success' => false,
            'message' => $message,
        ]);
    }

    public static function translateObjects($input)
    {
        if (is_object($input)) {
            if (method_exists($input, 'getData')) {
                return $input->getData();
            } elseif ($data = $input->data) {
                return static::translateObjects($data);
            }
            return $input;
        } elseif (is_array($input)) {
            foreach ($input as &$item) {
                $item = static::translateObjects($item);
            }
            return $input;
        } else {
            return $input;
        }
    }
}
