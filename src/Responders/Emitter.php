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

use Psr\Http\Message\ResponseInterface;

class Emitter
{
    protected ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    /**
     * Forked from Slim
     *
     * @return void
     */
    public function emit()
    {
        if (!headers_sent()) {
            foreach ($this->response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }

            // should come last just in case
            header(sprintf('HTTP/%s %s %s', $this->response->getProtocolVersion(), $this->response->getStatusCode(), $this->response->getReasonPhrase()));
        }

        $body = $this->response->getBody();
        if ($body->isSeekable()) {
            $body->rewind(); // just in case
        }

        $contentLength = $this->response->getHeaderLine('Content-Length');
        if (!$contentLength) {
            $contentLength = $body->getSize();
        }

        if (isset($contentLength)) {
            $amountToRead = $contentLength;
            while ($amountToRead > 0 && !$body->eof()) {
                $data = $body->read(min(4096, $amountToRead));
                echo $data;

                $amountToRead -= strlen($data);

                if (connection_status() != CONNECTION_NORMAL) {
                    break;
                }
            }
        } else {
            while (!$body->eof()) {
                echo $body->read(4096);
                if (connection_status() != CONNECTION_NORMAL) {
                    break;
                }
            }
        }
    }
}
