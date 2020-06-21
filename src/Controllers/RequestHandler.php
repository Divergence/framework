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

use Divergence\App;
use Divergence\Responders\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class RequestHandler implements RequestHandlerInterface
{
    public string $responseBuilder;

    public function peekPath()
    {
        return App::$App->Path->peekPath();
    }

    public function shiftPath()
    {
        return App::$App->Path->shiftPath();
    }

    public function unshiftPath($string)
    {
        return App::$App->Path->unshiftPath($string);
    }

    public function respond($responseID, $responseData = []): ResponseInterface
    {
        $className = $this->responseBuilder;
        return new Response(new $className($responseID, $responseData));
    }

    abstract public function handle(ServerRequestInterface $request): ResponseInterface;
}
