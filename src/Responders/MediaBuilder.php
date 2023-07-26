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
        if (isset($this->start)) {
            $fp = fopen($this->template, 'r');
            fseek($fp, $this->start);
            $stream = Utils::streamFor($fp);
            return Utils::streamFor($stream->getContents());
        }
        return Utils::streamFor(fopen($this->template, 'r'));
    }
}
