<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Divergence\Responders;

use Psr\Http\Message\ResponseInterface;
use function connection_status;
use function header;
use function headers_sent;
use function in_array;
use function min;
use function sprintf;
use function strlen;

use function strtolower;

use const CONNECTION_NORMAL;

/**
 * Forked from Slim Framework 4.x
 *
 */
class Emitter
{
    private int $responseChunkSize;

    protected ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function setResponseChunkSize($chunkSize): void
    {
        $this->responseChunkSize = $chunkSize;
    }

    /**
     * Send the response the client
     */
    public function emit(): void
    {
        if (!isset($this->responseChunkSize)) {
            $this->responseChunkSize= 4096;
        }
        $response = $this->response;
        $isEmpty = $this->isResponseEmpty($response);
        if (headers_sent() === false) {
            $this->emitHeaders($response);

            // Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
            // See https://github.com/slimphp/Slim/issues/1730

            $this->emitStatusLine($response);
        }

        if (!$isEmpty) {
            $this->emitBody($response);
        }
    }

    /**
     * Emit Response Headers
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $first = strtolower($name) !== 'set-cookie';
            foreach ($values as $value) {
                $header = sprintf('%s: %s', $name, $value);
                header($header, $first);
                $first = false;
            }
        }
    }

    /**
     * Emit Status Line
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusLine = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );
        header($statusLine, true, $response->getStatusCode());
    }

    /**
     * Emit Body
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        // partial content responses might have a pre-set stream seek
        if ($body->isSeekable() && $response->getStatusCode() !== 206) {
            $body->rewind();
        }

        $amountToRead = (int) $response->getHeaderLine('Content-Length');
        if (!$amountToRead) {
            $amountToRead = $body->getSize();
        }

        if ($amountToRead) {
            while ($amountToRead > 0 && !$body->eof()) {
                $length = min($this->responseChunkSize, $amountToRead);
                $data = $body->read($length);
                echo $data;

                $amountToRead -= strlen($data);

                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        } else {
            while (!$body->eof()) {
                echo $body->read($this->responseChunkSize);
                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        }
    }

    /**
     * Asserts response body is empty or status code is 204, 205 or 304
     */
    public function isResponseEmpty(ResponseInterface $response): bool
    {
        if (in_array($response->getStatusCode(), [204, 205, 304], true)) {
            return true;
        }
        if (in_array($response->getStatusCode(), [206], true)) {
            return false;
        }
        $stream = $response->getBody();
        $seekable = $stream->isSeekable();
        if ($seekable) {
            $stream->rewind();
        }
        return $seekable ? $stream->read(1) === '' : $stream->eof();
    }
}
