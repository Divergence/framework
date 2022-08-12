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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SiteRequestHandler extends RequestHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // @codeCoverageIgnoreStart
        phpinfo();
        exit;
        // @codeCoverageIgnoreEnd
    }
}
