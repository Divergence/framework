<?php

namespace Divergence\Responders;

class EmptyResponse extends Response
{
    public function __construct(ResponseBuilder $responseBuilder)
    {
        parent::__construct($responseBuilder);
    }
}
