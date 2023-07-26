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

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

class EmptyBuilder extends ResponseBuilder
{
    public function getBody(): StreamInterface
    {
        return Utils::streamFor('');
    }
}
