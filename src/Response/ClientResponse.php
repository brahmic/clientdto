<?php

namespace Brahmic\ClientDTO\Response;

use Brahmic\ClientDTO\Contracts\ClientResponseInterface;
use Illuminate\Http\Client\Response;

class ClientResponse implements ClientResponseInterface
{

    public function __construct(public readonly Response $response)
    {

    }

    public function isAttemptNeeded(): bool
    {
        return true;
    }
}
