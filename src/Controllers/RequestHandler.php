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
use BadMethodCallException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Base request handler with shared endpoint registration and dynamic endpoint dispatch.
 */
abstract class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var array<string, class-string>
     */
    protected $endpointClasses = [];

    /**
     * @var array<string, object>
     */
    protected $endpoints = [];

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

    /**
     * @param class-string $className
     * @param string|null $endpointName
     * @return void
     */
    protected function registerEndpointClass(string $className, ?string $endpointName = null): void
    {
        if ($endpointName === null) {
            $parts = explode('\\', $className);
            $endpointName = 'handle'.end($parts).'Request';
        }

        $endpointName = strtolower($endpointName);

        if (isset($this->endpointClasses[$endpointName])) {
            throw new Exception(sprintf('Endpoint method collision for %s', $endpointName));
        }

        $this->endpointClasses[$endpointName] = $className;
    }

    /**
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $endpointName = strtolower($name);

        if (!isset($this->endpointClasses[$endpointName])) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        if (!isset($this->endpoints[$endpointName])) {
            $endpointClass = $this->endpointClasses[$endpointName];
            $this->endpoints[$endpointName] = new $endpointClass($this);
        }

        return $this->endpoints[$endpointName]->handle(...$arguments);
    }

    abstract public function handle(ServerRequestInterface $request): ResponseInterface;
}
