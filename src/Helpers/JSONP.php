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
 * JSONP
 *
 * @package Divergence
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 */
class JSONP
{
    public static function respond($data)
    {
        $JSON = json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG);

        if (!$JSON) {
            throw new \Exception(json_last_error_msg());
        }
        header('Content-type: application/javascript; charset=utf-8', true);
        echo 'var data = ' . $JSON;
    }

    public static function translateAndRespond($data)
    {
        static::respond(JSON::translateObjects($data));
    }
}
