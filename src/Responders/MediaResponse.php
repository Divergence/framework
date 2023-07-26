<?php

namespace Divergence\Responders;

class MediaResponse extends Response
{
    public function __construct(ResponseBuilder $responseBuilder)
    {
        $this
        ->withDefaults(200, [
            'Content-Type' => $responseBuilder->getContentType(),
        ], $responseBuilder->getBody());
    }
}
