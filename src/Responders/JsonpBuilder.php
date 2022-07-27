<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Responders;

use GuzzleHttp\Psr7\Stream;
use Divergence\Helpers\JSON;
use Psr\Http\Message\StreamInterface;

class JsonpBuilder extends ResponseBuilder
{
    protected string $contentType = 'application/javascript; charset=utf-8';

    public function getBody(): StreamInterface
    {
        $output = 'var data = '.json_encode(JSON::translateObjects($this->data));
        return \GuzzleHttp\Psr7\stream_for($output);
    }
}
