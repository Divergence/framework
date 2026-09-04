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

class EmptyResponse extends Response
{
    public function __construct(ResponseBuilder $responseBuilder)
    {
        parent::__construct($responseBuilder);
    }
}
