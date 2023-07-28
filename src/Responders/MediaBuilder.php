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
use GuzzleHttp\Psr7\LimitStream;
use Psr\Http\Message\StreamInterface;

class MediaBuilder extends ResponseBuilder
{
    private ?int $start;
    private ?int $end;
    private ?int $length;

    public function setRange($start, $end, $length)
    {
        $this->start = $start;
        $this->end = $end;
        $this->length = $length;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getBody(): StreamInterface
    {
        $fp = new Stream(fopen($this->template, 'r'));
        if (isset($this->start) && $this->start>0) {
            return new LimitStream($fp, $this->length, $this->start);
        }
        return $fp;
    }
}
