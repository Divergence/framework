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

use Psr\Http\Message\StreamInterface;

abstract class ResponseBuilder
{
    protected $template;
    protected string $contentType = '';
    protected array $headers;
    protected array $data;

    public function __construct($template, $data = [], $headers = [])
    {
        $this->template = $template;
        $this->data = $data;
        $this->headers = $headers;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }


    public function getContentType(): string
    {
        return $this->contentType;
    }

    abstract public function getBody(): StreamInterface;
}
